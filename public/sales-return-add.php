<?php
require_once __DIR__.'/../src/core.php';
$user=require_auth('cashier');
$page_title='New Sales Return';
$tid=tid();$bid=brid();
$cs_stmt=db()->prepare('SELECT * FROM company_settings WHERE tenant_id=?');$cs_stmt->execute([$tid]);$cs=$cs_stmt->fetch()?:[];
$currency_sym=!empty($cs['currency'])?$cs['currency']:CURRENCY;

if($_SERVER['REQUEST_METHOD']==='POST'){
  $data=$_POST; $rows=[]; $subtotal=0;
  foreach(($data['items']['description']??[]) as $i=>$desc){
    if(trim($desc)==='')continue;
    $qty=(float)($data['items']['qty'][$i]??1);
    $price=(float)($data['items']['unit_price'][$i]??0);
    $item_id=(int)($data['items']['item_id'][$i]??0)?:null;
    $rows[]=['desc'=>$desc,'qty'=>$qty,'price'=>$price,'total'=>$qty*$price,'item_id'=>$item_id];
    $subtotal+=$qty*$price;
  }
  // Generate return number
  $seq=db()->prepare('INSERT INTO invoice_sequences (tenant_id,branch_id,prefix,last_number) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE last_number=LAST_INSERT_ID(last_number+1)');
  $seq->execute([$tid,$bid,'SR']);
  $num=(int)db()->lastInsertId();if($num<1){$r2=db()->prepare('SELECT last_number FROM invoice_sequences WHERE tenant_id=? AND branch_id=? AND prefix="SR"');$r2->execute([$tid,$bid]);$num=(int)$r2->fetchColumn();}
  $return_no='SR-'.str_pad($num,5,'0',STR_PAD_LEFT);

  $cust_id=($data['customer_id']??'')?:null;
  $cust_name=trim($data['customer_name']??'');

  db()->prepare('INSERT INTO sales_returns (tenant_id,branch_id,income_id,return_no,customer_id,customer_name,date,total,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)')
    ->execute([$tid,$bid,($data['income_id']??null)?:null,$return_no,$cust_id,$cust_name,$data['date'],$subtotal,$data['notes']??'',uid()]);
  $ret_id=(int)db()->lastInsertId();

  $ins=db()->prepare('INSERT INTO sales_return_items (return_id,item_id,description,qty,unit_price) VALUES (?,?,?,?,?)');
  foreach($rows as $r){
    $ins->execute([$ret_id,$r['item_id'],$r['desc'],$r['qty'],$r['price']]);
    // Restore stock
    if($r['item_id'])db()->prepare('UPDATE items SET quantity=quantity+? WHERE id=? AND tenant_id=?')->execute([$r['qty'],$r['item_id'],$tid]);
  }
  flash('success',"Sales Return $return_no created");
  redirect('sales-returns.php');
}
$today=date('Y-m-d');
ob_start();
?>
<form method="post">
<div class="card">
  <div class="card-title"><span class="t-en">Return Details</span><span class="t-bn">ফেরতের বিবরণ</span></div>
  <div class="form-grid">
    <div class="form-group"><label>Customer Name</label><input type="text" name="customer_name" placeholder="Customer name"></div>
    <input type="hidden" name="customer_id" value="">
    <div class="form-group"><label>Original Invoice ID (optional)</label><input type="number" name="income_id" placeholder="Invoice ID"></div>
    <div class="form-group"><label>Date</label><input type="date" name="date" value="<?= $today ?>" required></div>
  </div>
</div>
<div class="card">
  <div class="card-title">Return Items</div>
  <div class="table-wrap" style="overflow-x:auto">
    <table id="items_table" style="width:100%;table-layout:fixed">
      <thead><tr><th style="width:45%">Description</th><th style="width:15%">Qty</th><th style="width:20%">Unit Price (<?= $currency_sym ?>)</th><th style="width:15%;text-align:right">Total</th><th style="width:5%"></th></tr></thead>
      <tbody id="items_body">
        <tr>
          <td><input name="items[description][]" placeholder="Item name" required style="width:100%;box-sizing:border-box"><input type="hidden" name="items[item_id][]" value=""></td>
          <td><input name="items[qty][]" type="number" step="0.001" value="1" style="width:100%;box-sizing:border-box" oninput="calcRet()"></td>
          <td><input name="items[unit_price][]" type="number" step="0.01" value="0.00" style="width:100%;box-sizing:border-box" oninput="calcRet()"></td>
          <td class="row-total" style="text-align:right;font-weight:600">0.00</td>
          <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();calcRet()">✕</button></td>
        </tr>
      </tbody>
    </table>
  </div>
  <button type="button" class="btn btn-outline" style="margin-top:10px" id="btn_add_line">+ Add Line</button>
</div>
<div class="card">
  <div style="text-align:right;font-size:18px;font-weight:700">Total: <?= $currency_sym ?> <span id="ret_total">0.00</span></div>
  <div class="form-group" style="margin-top:12px"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
</div>
<div style="display:flex;gap:10px">
  <button type="submit" class="btn btn-primary">💾 Save Return</button>
  <a href="sales-returns.php" class="btn btn-outline">Cancel</a>
</div>
</form>
<script>
function calcRet(){
  var sub=0;
  document.querySelectorAll('#items_body tr').forEach(function(tr){
    var q=parseFloat(tr.querySelector('[name="items[qty][]"]')?tr.querySelector('[name="items[qty][]"]').value:0)||0;
    var p=parseFloat(tr.querySelector('[name="items[unit_price][]"]')?tr.querySelector('[name="items[unit_price][]"]').value:0)||0;
    var t=q*p;var tc=tr.querySelector('.row-total');if(tc)tc.textContent=t.toFixed(2);sub+=t;
  });
  var el=document.getElementById('ret_total');if(el)el.textContent=sub.toFixed(2);
}
document.getElementById('btn_add_line').addEventListener('click',function(){
  var tbody=document.getElementById('items_body');
  var tr=document.createElement('tr');
  var td1=document.createElement('td');var i1=document.createElement('input');i1.name='items[description][]';i1.placeholder='Item name';i1.required=true;i1.style.cssText='width:100%;box-sizing:border-box';var h1=document.createElement('input');h1.type='hidden';h1.name='items[item_id][]';h1.value='';td1.appendChild(i1);td1.appendChild(h1);
  var td2=document.createElement('td');var i2=document.createElement('input');i2.name='items[qty][]';i2.type='number';i2.step='0.001';i2.value='1';i2.style.cssText='width:100%;box-sizing:border-box';i2.addEventListener('input',calcRet);td2.appendChild(i2);
  var td3=document.createElement('td');var i3=document.createElement('input');i3.name='items[unit_price][]';i3.type='number';i3.step='0.01';i3.value='0.00';i3.style.cssText='width:100%;box-sizing:border-box';i3.addEventListener('input',calcRet);td3.appendChild(i3);
  var td4=document.createElement('td');td4.className='row-total';td4.style.cssText='text-align:right;font-weight:600';td4.textContent='0.00';
  var td5=document.createElement('td');var btn=document.createElement('button');btn.type='button';btn.className='btn btn-danger btn-sm';btn.textContent='✕';btn.addEventListener('click',function(){tr.remove();calcRet();});td5.appendChild(btn);
  tr.appendChild(td1);tr.appendChild(td2);tr.appendChild(td3);tr.appendChild(td4);tr.appendChild(td5);tbody.appendChild(tr);
});
</script>
<?php $content=ob_get_clean();include __DIR__.'/../templates/layout.php';
