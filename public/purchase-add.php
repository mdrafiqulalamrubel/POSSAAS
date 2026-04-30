<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth('cashier');
$tid = tid(); $bid = brid();
$edit_id = (int)($_GET['id'] ?? 0);
$page_title = $edit_id ? 'Edit Purchase' : 'New Purchase';

$cs = db()->prepare('SELECT * FROM company_settings WHERE tenant_id=?');
$cs->execute([$tid]); $cs = $cs->fetch() ?: [];
$cur = !empty($cs['currency']) ? $cs['currency'] : CURRENCY;

// Load for edit
$pur = null; $pur_items = [];
if ($edit_id) {
    $s = db()->prepare('SELECT * FROM purchases WHERE id=? AND tenant_id=? AND branch_id=?');
    $s->execute([$edit_id,$tid,$bid]); $pur = $s->fetch();
    if (!$pur) { flash('error','Purchase not found'); redirect('purchases.php'); }
    $si = db()->prepare('SELECT * FROM purchase_items WHERE purchase_id=?');
    $si->execute([$edit_id]); $pur_items = $si->fetchAll();
}

// ── SAVE ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data  = $_POST;
    $rows  = [];
    $subtotal = 0;
    foreach (($data['items']['description'] ?? []) as $i => $desc) {
        if (trim($desc) === '') continue;
        $qty     = (float)($data['items']['qty'][$i]        ?? 1);
        $price   = (float)($data['items']['unit_price'][$i] ?? 0);
        $item_id = (int)($data['items']['item_id'][$i]      ?? 0) ?: null;
        $rows[]  = ['desc'=>$desc,'qty'=>$qty,'price'=>$price,'item_id'=>$item_id];
        $subtotal += $qty * $price;
    }
    $tax_pct  = (float)($data['tax_pct']  ?? 0);
    $discount = (float)($data['discount'] ?? 0);
    $tax_amt  = $subtotal * $tax_pct / 100;
    $total    = $subtotal + $tax_amt - $discount;
    $paid     = (float)($data['paid'] ?? 0);
    $status   = $data['status'] ?? 'received';

    $supp_id   = (int)($data['supplier_id'] ?? 0) ?: null;
    $supp_name = trim($data['supplier_name'] ?? '');

    // Auto-save supplier
    if ($supp_name && !$supp_id) {
        $chk = db()->prepare('SELECT id FROM suppliers WHERE tenant_id=? AND name=? LIMIT 1');
        $chk->execute([$tid,$supp_name]);
        $supp_id = $chk->fetchColumn() ?: null;
        if (!$supp_id) {
            db()->prepare('INSERT INTO suppliers (tenant_id,name) VALUES (?,?)')->execute([$tid,$supp_name]);
            $supp_id = (int)db()->lastInsertId();
        }
    }

    db()->beginTransaction();

    if ($edit_id) {
        // Restore stock for old items
        $old = db()->prepare('SELECT item_id, qty FROM purchase_items WHERE purchase_id=? AND item_id IS NOT NULL');
        $old->execute([$edit_id]);
        foreach ($old->fetchAll() as $o) {
            db()->prepare('UPDATE items SET quantity = GREATEST(0, quantity - ?) WHERE id=? AND tenant_id=?')
               ->execute([$o['qty'], $o['item_id'], $tid]);
        }
        db()->prepare('UPDATE purchases SET supplier_id=?,supplier_name=?,date=?,subtotal=?,tax_pct=?,
                       tax_amount=?,discount=?,total=?,paid=?,status=?,notes=? WHERE id=? AND tenant_id=?')
           ->execute([$supp_id,$supp_name,$data['date'],$subtotal,$tax_pct,
                      $tax_amt,$discount,$total,$paid,$status,$data['notes']??'',$edit_id,$tid]);
        db()->prepare('DELETE FROM purchase_items WHERE purchase_id=?')->execute([$edit_id]);
        $pur_id = $edit_id;
    } else {
        $ref_no = next_purchase_no($bid);
        db()->prepare('INSERT INTO purchases (tenant_id,branch_id,ref_no,supplier_id,supplier_name,
                       date,subtotal,tax_pct,tax_amount,discount,total,paid,status,notes,created_by)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
           ->execute([$tid,$bid,$ref_no,$supp_id,$supp_name,$data['date'],$subtotal,$tax_pct,
                      $tax_amt,$discount,$total,$paid,$status,$data['notes']??'',uid()]);
        $pur_id = (int)db()->lastInsertId();
    }

    // Insert line items + ADD to stock
    $ins = db()->prepare('INSERT INTO purchase_items (purchase_id,item_id,description,qty,unit_price) VALUES (?,?,?,?,?)');
    foreach ($rows as $r) {
        $ins->execute([$pur_id,$r['item_id'],$r['desc'],$r['qty'],$r['price']]);
        if ($r['item_id'] && $status !== 'cancelled') {
            db()->prepare('UPDATE items SET quantity = quantity + ?, unit_price = ? WHERE id=? AND tenant_id=?')
               ->execute([$r['qty'], $r['price'], $r['item_id'], $tid]);
        }
    }
    db()->commit();

    flash('success', $edit_id ? 'Purchase updated.' : "Purchase created: $ref_no");
    redirect('purchase-view.php?id=' . $pur_id);
}

$today = date('Y-m-d');
$suppliers = db()->prepare('SELECT * FROM suppliers WHERE tenant_id=? AND is_active=1 ORDER BY name');
$suppliers->execute([$tid]); $suppliers = $suppliers->fetchAll();

ob_start();
?>
<style>
#supp_dropdown{display:none;position:absolute;top:calc(100% + 2px);left:0;right:0;background:var(--c-card);border:1px solid var(--c-border);border-radius:var(--radius);box-shadow:0 6px 20px rgba(0,0,0,.12);z-index:1000;max-height:220px;overflow-y:auto}
.supp-item{padding:9px 14px;cursor:pointer;border-bottom:1px solid var(--c-border);font-size:13px}
.supp-item:hover{background:var(--c-bg)}
</style>

<form method="post" id="pur_form">

<!-- Supplier -->
<div class="card">
  <div class="card-title">🏭 Supplier</div>
  <div class="form-grid">
    <div class="form-group" style="position:relative;grid-column:1/-1">
      <label>Search / Add Supplier</label>
      <input type="text" id="supp_search" placeholder="Type supplier name…" autocomplete="off"
             oninput="suppSearch(this.value)" value="<?= h($pur['supplier_name']??'') ?>">
      <div id="supp_dropdown"></div>
      <input type="hidden" name="supplier_id"   id="f_supp_id"   value="<?= h($pur['supplier_id']??'') ?>">
      <input type="hidden" name="supplier_name" id="f_supp_name" value="<?= h($pur['supplier_name']??'') ?>">
      <div id="supp_tag" style="margin-top:6px;font-size:13px;color:var(--c-income);display:<?= ($pur['supplier_name']??'')?'block':'none' ?>">
        ✅ <span id="supp_tag_name"><?= h($pur['supplier_name']??'') ?></span>
        — <a href="#" onclick="clearSupplier();return false" style="color:var(--c-expense);font-size:12px">✕ Change</a>
      </div>
    </div>
  </div>
</div>

<!-- Purchase Meta -->
<div class="card">
  <div class="form-grid">
    <div class="form-group">
      <label>Purchase Date *</label>
      <input type="date" name="date" required value="<?= h($pur['date']??$today) ?>">
    </div>
    <div class="form-group">
      <label>Status</label>
      <select name="status">
        <?php foreach (['received','partial','paid','draft','cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= ($pur['status']??'received')==$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Amount Paid (<?= $cur ?>)</label>
      <input type="number" step="0.01" name="paid" value="<?= h($pur['paid']??'0') ?>">
    </div>
  </div>
</div>

<!-- Item Search -->
<div class="card" style="border:2px solid var(--c-primary,#4f46e5)">
  <div class="card-title" style="margin-bottom:8px">🔍 Search Item to Add</div>
  <div style="display:flex;gap:8px">
    <input type="text" id="item_search" placeholder="Type item name or SKU…" autocomplete="off"
           oninput="itemSearch(this.value)" style="flex:1">
    <span id="item_spinner" style="display:none;font-size:18px">⏳</span>
  </div>
  <div id="item_dropdown" style="background:var(--c-card);border:1px solid var(--c-border);border-radius:var(--radius);box-shadow:0 4px 16px rgba(0,0,0,.1);z-index:100;display:none;margin-top:4px"></div>

  <!-- Found panel -->
  <div id="step_found" style="display:none;margin-top:8px;padding:12px;background:var(--c-bg);border:1px solid var(--c-income,#16a34a);border-radius:var(--radius)">
    <div style="font-weight:700;color:var(--c-income);margin-bottom:8px">✅ Item Found</div>
    <div style="display:grid;grid-template-columns:1fr auto auto;gap:8px;align-items:end">
      <div>
        <label style="font-size:12px;color:var(--c-muted)">Item</label>
        <div id="found_name" style="font-size:15px;font-weight:700;padding:4px 0"></div>
      </div>
      <div>
        <label style="font-size:12px;color:var(--c-muted)">Qty</label>
        <input type="number" id="found_qty" value="1" min="0.001" step="0.001" style="width:72px;font-size:15px;font-weight:700;border:2px solid var(--c-income);border-radius:var(--radius);padding:5px 8px">
      </div>
      <div>
        <label style="font-size:12px;color:var(--c-muted)">Cost Price (<?= $cur ?>)</label>
        <input type="number" id="found_price" value="0.00" step="0.01" style="width:100px;font-size:15px;font-weight:700;border:2px solid var(--c-income);border-radius:var(--radius);padding:5px 8px">
      </div>
    </div>
    <div id="found_stock" style="font-size:12px;margin-top:4px;color:var(--c-muted)"></div>
    <div style="margin-top:10px;display:flex;gap:8px">
      <button type="button" class="btn btn-primary btn-sm" onclick="confirmAdd()">➕ Add to Purchase</button>
      <button type="button" class="btn btn-outline btn-sm" onclick="resetSearch()">✕ Cancel</button>
    </div>
  </div>

  <!-- New item panel -->
  <div id="new_item_panel" style="display:none;margin-top:8px;padding:12px;background:var(--c-bg);border:1px solid var(--c-warn,#f59e0b);border-radius:var(--radius)">
    <div style="font-weight:700;color:var(--c-warn);margin-bottom:8px">⚠ Not in inventory — add new item?</div>
    <div class="form-grid">
      <div class="form-group" style="margin-bottom:8px"><label>Item Name *</label><input type="text" id="new_name" placeholder="Name"></div>
      <div class="form-group" style="margin-bottom:8px"><label>SKU / Barcode</label><input type="text" id="new_sku"></div>
      <div class="form-group" style="margin-bottom:8px"><label>Cost Price</label><input type="number" id="new_price" step="0.01" value="0.00"></div>
      <div class="form-group" style="margin-bottom:8px"><label>Qty Received</label><input type="number" id="new_qty" step="0.001" value="1"></div>
      <div class="form-group" style="margin-bottom:8px"><label>Unit</label>
        <select id="new_unit"><?php foreach(['pcs','kg','g','litre','ml','box','dozen','pack','bottle','bag'] as $u): ?><option value="<?=$u?>"><?=$u?></option><?php endforeach; ?></select>
      </div>
    </div>
    <div style="display:flex;gap:8px">
      <button type="button" class="btn btn-primary btn-sm" onclick="saveNewAndAdd()">💾 Save to Inventory & Add</button>
      <button type="button" class="btn btn-outline btn-sm" onclick="addManual()">Add Without Saving</button>
      <button type="button" class="btn btn-danger btn-sm" onclick="resetSearch()">✕ Cancel</button>
    </div>
  </div>
</div>

<!-- Line Items Table -->
<div class="card">
  <div class="card-title">Purchase Items</div>
  <div class="table-wrap">
    <table id="pur_table" style="width:100%;table-layout:fixed">
      <thead>
        <tr>
          <th style="width:40%">Description</th>
          <th style="width:12%">Qty</th>
          <th style="width:18%">Cost Price (<?= $cur ?>)</th>
          <th style="width:16%;text-align:right">Total</th>
          <th style="width:8%;text-align:center">Stock</th>
          <th style="width:6%;text-align:center"></th>
        </tr>
      </thead>
      <tbody id="pur_body">
      <?php if ($pur_items): foreach ($pur_items as $it): ?>
        <tr>
          <td><input name="items[description][]" value="<?= h($it['description']) ?>" required style="width:100%;box-sizing:border-box">
              <input type="hidden" name="items[item_id][]" value="<?= h($it['item_id']??'') ?>"></td>
          <td><input name="items[qty][]" type="number" step="0.001" value="<?= h($it['qty']) ?>" style="width:100%;box-sizing:border-box" oninput="purCalc()"></td>
          <td><input name="items[unit_price][]" type="number" step="0.01" value="<?= h($it['unit_price']) ?>" style="width:100%;box-sizing:border-box" oninput="purCalc()"></td>
          <td class="item-total" style="text-align:right;font-weight:600"><?= $cur.number_format($it['total'],2) ?></td>
          <td class="item-stock" style="text-align:center;font-size:11px;color:var(--c-muted)">—</td>
          <td style="text-align:center"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();purCalc()">✕</button></td>
        </tr>
      <?php endforeach; else: ?>
        <tr>
          <td><input name="items[description][]" placeholder="Item description" required style="width:100%;box-sizing:border-box">
              <input type="hidden" name="items[item_id][]" value=""></td>
          <td><input name="items[qty][]" type="number" step="0.001" value="1" style="width:100%;box-sizing:border-box" oninput="purCalc()"></td>
          <td><input name="items[unit_price][]" type="number" step="0.01" value="0.00" style="width:100%;box-sizing:border-box" oninput="purCalc()"></td>
          <td class="item-total" style="text-align:right;font-weight:600">0.00</td>
          <td class="item-stock" style="text-align:center;font-size:11px;color:var(--c-muted)">—</td>
          <td style="text-align:center"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();purCalc()">✕</button></td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <button type="button" class="btn btn-outline" style="margin-top:10px" onclick="purAddRow()">+ Add Line</button>
</div>

<!-- Totals + Notes -->
<div style="display:grid;grid-template-columns:1fr minmax(0,300px);gap:20px;align-items:start">
  <div class="card" style="min-width:0">
    <div class="card-title">Notes</div>
    <textarea name="notes" rows="4" style="width:100%;box-sizing:border-box" placeholder="Optional notes…"><?= h($pur['notes']??'') ?></textarea>
  </div>
  <div class="card" style="min-width:0">
    <table style="width:100%;table-layout:fixed"><col style="width:55%"><col style="width:45%">
      <tr><td>Subtotal</td><td style="text-align:right"><?= $cur ?> <span id="disp_sub"><?= number_format($pur['subtotal']??0,2) ?></span></td></tr>
      <tr><td>Tax %: <input type="number" step="0.01" id="tax_pct" name="tax_pct" value="<?= h($pur['tax_pct']??'0') ?>" style="width:52px" oninput="purCalc()"></td>
          <td style="text-align:right"><?= $cur ?> <span id="disp_tax"><?= number_format($pur['tax_amount']??0,2) ?></span></td></tr>
      <tr><td>Discount</td>
          <td style="text-align:right"><input type="number" step="0.01" id="discount" name="discount" value="<?= h($pur['discount']??'0') ?>" style="width:80px;text-align:right;box-sizing:border-box" oninput="purCalc()"></td></tr>
      <tr class="grand-total"><td><strong>Total</strong></td>
          <td style="text-align:right"><strong><?= $cur ?> <span id="disp_total"><?= number_format($pur['total']??0,2) ?></span></strong></td></tr>
    </table>
  </div>
</div>

<div style="display:flex;gap:10px;margin-top:8px">
  <button type="submit" class="btn btn-primary">💾 Save Purchase</button>
  <a href="<?= APP_URL ?>/purchases.php" class="btn btn-outline">Cancel</a>
</div>
</form>

<script>
const APP_URL = '<?= APP_URL ?>';
const CUR = '<?= addslashes($cur) ?>';

// ── Supplier autocomplete ─────────────────────────────────────
var suppTimer = null, suppResults = [];
function suppSearch(q) {
  clearTimeout(suppTimer);
  var dd = document.getElementById('supp_dropdown');
  if (!q.trim()) { dd.style.display='none'; return; }
  suppTimer = setTimeout(function(){
    fetch(APP_URL+'/api-suppliers-search.php?q='+encodeURIComponent(q))
      .then(r=>r.json()).then(data=>{
        suppResults = data;
        dd.innerHTML='';
        data.forEach(function(s,i){
          var d=document.createElement('div'); d.className='supp-item';
          d.innerHTML='<strong>'+esc(s.name)+'</strong>'+(s.phone?' 📞'+esc(s.phone):'');
          d.addEventListener('click',()=>selectSupplier(i)); dd.appendChild(d);
        });
        var addNew=document.createElement('div'); addNew.className='supp-item';
        addNew.style.color='var(--c-income)'; addNew.textContent='➕ Use "'+q+'" as new supplier';
        addNew.addEventListener('click',()=>useSupplierName(q)); dd.appendChild(addNew);
        dd.style.display='block';
      });
  },200);
}
function selectSupplier(i){
  var s=suppResults[i];
  document.getElementById('f_supp_id').value=s.id;
  document.getElementById('f_supp_name').value=s.name;
  document.getElementById('supp_search').value=s.name;
  document.getElementById('supp_tag_name').textContent=s.name;
  document.getElementById('supp_tag').style.display='block';
  document.getElementById('supp_dropdown').style.display='none';
}
function useSupplierName(q){
  document.getElementById('f_supp_id').value='';
  document.getElementById('f_supp_name').value=q;
  document.getElementById('supp_search').value=q;
  document.getElementById('supp_tag_name').textContent=q+' (new)';
  document.getElementById('supp_tag').style.display='block';
  document.getElementById('supp_dropdown').style.display='none';
}
function clearSupplier(){
  document.getElementById('f_supp_id').value='';
  document.getElementById('f_supp_name').value='';
  document.getElementById('supp_search').value='';
  document.getElementById('supp_tag').style.display='none';
  document.getElementById('supp_search').focus();
}
document.addEventListener('click',e=>{
  if(!e.target.closest('#supp_search')&&!e.target.closest('#supp_dropdown'))
    document.getElementById('supp_dropdown').style.display='none';
});

// ── Item search ───────────────────────────────────────────────
var itemTimer=null,itemResults=[],foundItem=null,lastQ='';
function itemSearch(q){
  clearTimeout(itemTimer); q=q.trim();
  var dd=document.getElementById('item_dropdown');
  if(!q){dd.style.display='none';return;}
  lastQ=q;
  document.getElementById('item_spinner').style.display='inline';
  itemTimer=setTimeout(function(){
    fetch(APP_URL+'/api-items-search.php?q='+encodeURIComponent(q))
      .then(r=>r.json()).then(data=>{
        document.getElementById('item_spinner').style.display='none';
        itemResults=data; dd.innerHTML='';
        var exact=data.find(x=>x.sku&&x.sku.toLowerCase()===q.toLowerCase());
        if(exact){dd.style.display='none';showFound(exact);return;}
        if(!data.length){showNewItemPanel(q);return;}
        data.forEach(it=>{
          var row=document.createElement('div');
          row.style.cssText='padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--c-border);display:flex;justify-content:space-between';
          row.innerHTML='<div><strong>'+esc(it.name)+'</strong>'+(it.sku?' <span style="font-size:11px;color:var(--c-muted)">['+esc(it.sku)+']</span>':'')+'</div>'
            +'<div style="text-align:right"><div style="font-weight:700">'+CUR+parseFloat(it.unit_price).toFixed(2)+'</div>'
            +'<div style="font-size:11px;color:var(--c-income)">Stock: '+parseFloat(it.quantity).toFixed(2)+' '+esc(it.unit)+'</div></div>';
          row.addEventListener('click',()=>{dd.style.display='none';showFound(it);});
          dd.appendChild(row);
        });
        var addNew=document.createElement('div');
        addNew.style.cssText='padding:10px 14px;cursor:pointer;color:var(--c-income);font-size:13px';
        addNew.textContent='➕ New item not listed…';
        addNew.addEventListener('click',()=>showNewItemPanel(q));
        dd.appendChild(addNew);
        dd.style.display='block';
      }).catch(()=>document.getElementById('item_spinner').style.display='none');
  },220);
}
function showFound(it){
  foundItem=it;
  document.getElementById('item_dropdown').style.display='none';
  document.getElementById('new_item_panel').style.display='none';
  document.getElementById('found_name').textContent=it.name+(it.sku?' ['+it.sku+']':'');
  document.getElementById('found_qty').value=1;
  document.getElementById('found_price').value=parseFloat(it.unit_price).toFixed(2);
  document.getElementById('found_stock').textContent='Current stock: '+parseFloat(it.quantity).toFixed(2)+' '+it.unit;
  document.getElementById('step_found').style.display='block';
  document.getElementById('found_qty').select();
}
function confirmAdd(){
  if(!foundItem) return;
  var qty=parseFloat(document.getElementById('found_qty').value)||1;
  var price=parseFloat(document.getElementById('found_price').value)||0;
  purAddRowFull(foundItem.name,qty,price,foundItem.id,parseFloat(foundItem.quantity),foundItem.unit);
  resetSearch();
}
function showNewItemPanel(q){
  document.getElementById('item_dropdown').style.display='none';
  document.getElementById('step_found').style.display='none';
  document.getElementById('new_item_panel').style.display='block';
  document.getElementById('new_name').value=q;
  document.getElementById('new_price').focus();
}
function saveNewAndAdd(){
  var name=document.getElementById('new_name').value.trim();
  if(!name){alert('Item name required');return;}
  var fd=new FormData();
  fd.append('name',name);
  fd.append('sku',document.getElementById('new_sku').value.trim());
  fd.append('unit_price',document.getElementById('new_price').value);
  fd.append('unit',document.getElementById('new_unit').value);
  fetch(APP_URL+'/api-save-item.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(data=>{
      if(data.error){alert(data.error);return;}
      var qty=parseFloat(document.getElementById('new_qty').value)||1;
      purAddRowFull(data.name,qty,parseFloat(data.unit_price),data.id,0,data.unit);
      resetSearch();
    });
}
function addManual(){
  var name=document.getElementById('new_name').value.trim();
  var price=parseFloat(document.getElementById('new_price').value)||0;
  var qty=parseFloat(document.getElementById('new_qty').value)||1;
  if(!name){alert('Item name required');return;}
  purAddRowFull(name,qty,price,null,null,'');
  resetSearch();
}
function resetSearch(){
  foundItem=null;itemResults=[];lastQ='';
  document.getElementById('item_search').value='';
  document.getElementById('item_dropdown').style.display='none';
  document.getElementById('step_found').style.display='none';
  document.getElementById('new_item_panel').style.display='none';
  document.getElementById('item_search').focus();
}
document.addEventListener('click',e=>{
  if(!e.target.closest('#item_search')&&!e.target.closest('#item_dropdown')&&
     !e.target.closest('#step_found')&&!e.target.closest('#new_item_panel'))
    document.getElementById('item_dropdown').style.display='none';
});

// ── Line item table ───────────────────────────────────────────
function purAddRow(){
  var tbody=document.getElementById('pur_body');
  var tr=document.createElement('tr');
  tr.innerHTML=`
    <td><input name="items[description][]" placeholder="Description" required style="width:100%;box-sizing:border-box">
        <input type="hidden" name="items[item_id][]" value=""></td>
    <td><input name="items[qty][]" type="number" step="0.001" value="1" style="width:100%;box-sizing:border-box" oninput="purCalc()"></td>
    <td><input name="items[unit_price][]" type="number" step="0.01" value="0.00" style="width:100%;box-sizing:border-box" oninput="purCalc()"></td>
    <td class="item-total" style="text-align:right;font-weight:600">0.00</td>
    <td class="item-stock" style="text-align:center;font-size:11px;color:var(--c-muted)">—</td>
    <td style="text-align:center"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();purCalc()">✕</button></td>`;
  tbody.appendChild(tr); purCalc(); return tr;
}
function purAddRowFull(name,qty,price,itemId,stock,unit){
  var tr=purAddRow();
  tr.querySelector('[name="items[description][]"]').value=name;
  tr.querySelector('[name="items[qty][]"]').value=qty;
  tr.querySelector('[name="items[unit_price][]"]').value=parseFloat(price).toFixed(2);
  tr.querySelector('[name="items[item_id][]"]').value=itemId||'';
  var sc=tr.querySelector('.item-stock');
  if(stock!==null&&stock!==undefined){
    sc.textContent=stock.toFixed(1)+' '+(unit||'');
    sc.style.color='var(--c-income)';
  }
  purCalc();
}
function purCalc(){
  var sub=0;
  document.querySelectorAll('#pur_body tr').forEach(tr=>{
    var q=parseFloat(tr.querySelector('[name="items[qty][]"]')?.value)||0;
    var p=parseFloat(tr.querySelector('[name="items[unit_price][]"]')?.value)||0;
    var t=q*p; sub+=t;
    var tc=tr.querySelector('.item-total'); if(tc) tc.textContent=t.toFixed(2);
  });
  var tax=parseFloat(document.getElementById('tax_pct')?.value)||0;
  var disc=parseFloat(document.getElementById('discount')?.value)||0;
  var taxAmt=sub*tax/100; var total=sub+taxAmt-disc;
  var se=document.getElementById('disp_sub'); if(se) se.textContent=sub.toFixed(2);
  var te=document.getElementById('disp_tax'); if(te) te.textContent=taxAmt.toFixed(2);
  var tot=document.getElementById('disp_total'); if(tot) tot.textContent=total.toFixed(2);
}
function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

window.addEventListener('load',()=>purCalc());
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
