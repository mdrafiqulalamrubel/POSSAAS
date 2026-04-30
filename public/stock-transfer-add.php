<?php
require_once __DIR__.'/../src/core.php';
$user=require_auth('admin');$page_title='New Stock Transfer';$tid=tid();$bid=brid();
$all_branches=db()->prepare('SELECT * FROM branches WHERE tenant_id=? AND is_active=1 ORDER BY name');$all_branches->execute([$tid]);$all_branches=$all_branches->fetchAll();

if($_SERVER['REQUEST_METHOD']==='POST'){
  $data=$_POST;
  $from_bid=(int)($data['from_branch_id']??0);
  $to_bid=(int)($data['to_branch_id']??0);
  if($from_bid===$to_bid){flash('error','From and To branch must be different');redirect('stock-transfer-add.php');}

  $rows=[];
  foreach(($data['items']['item_id']??[]) as $i=>$item_id){
    $item_id=(int)$item_id;if(!$item_id)continue;
    $qty=(float)($data['items']['qty'][$i]??0);if($qty<=0)continue;
    $rows[]=['item_id'=>$item_id,'desc'=>$data['items']['description'][$i]??'','qty'=>$qty];
  }
  if(!$rows){flash('error','No items to transfer');redirect('stock-transfer-add.php');}

  $seq=db()->prepare('INSERT INTO invoice_sequences (tenant_id,branch_id,prefix,last_number) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE last_number=LAST_INSERT_ID(last_number+1)');
  $seq->execute([$tid,$from_bid,'TRF']);$num=(int)db()->lastInsertId();
  $transfer_no='TRF-'.str_pad($num,5,'0',STR_PAD_LEFT);

  db()->prepare('INSERT INTO stock_transfers (tenant_id,from_branch_id,to_branch_id,transfer_no,date,status,notes,created_by) VALUES (?,?,?,?,?,?,?,?)')
    ->execute([$tid,$from_bid,$to_bid,$transfer_no,$data['date'],'completed',$data['notes']??'',uid()]);
  $tid2=(int)db()->lastInsertId();

  $ins=db()->prepare('INSERT INTO stock_transfer_items (transfer_id,item_id,description,qty) VALUES (?,?,?,?)');
  foreach($rows as $r){
    $ins->execute([$tid2,$r['item_id'],$r['desc'],$r['qty']]);
    db()->prepare('UPDATE items SET quantity=GREATEST(0,quantity-?) WHERE id=? AND tenant_id=? AND branch_id=?')->execute([$r['qty'],$r['item_id'],$tid,$from_bid]);
    // Add to destination branch item (upsert)
    $src=db()->prepare('SELECT * FROM items WHERE id=? AND tenant_id=?');$src->execute([$r['item_id'],$tid]);$src_item=$src->fetch();
    if($src_item){
      $dest=db()->prepare('SELECT id FROM items WHERE tenant_id=? AND branch_id=? AND sku=? LIMIT 1');
      $dest->execute([$tid,$to_bid,$src_item['sku']?:'__NO_SKU__'.$r['item_id']]);
      $dest_id=$dest->fetchColumn();
      if($dest_id){db()->prepare('UPDATE items SET quantity=quantity+? WHERE id=?')->execute([$r['qty'],$dest_id]);}
      else{db()->prepare('INSERT INTO items (tenant_id,branch_id,name,sku,category,unit,quantity,unit_price,is_active,created_by) VALUES (?,?,?,?,?,?,?,?,1,?)')->execute([$tid,$to_bid,$src_item['name'],$src_item['sku'],$src_item['category'],$src_item['unit'],$r['qty'],$src_item['unit_price'],uid()]);}
    }
  }
  flash('success',"Transfer $transfer_no completed");redirect('stock-transfers.php');
}

// Load items of current from-branch
$from_bid_sel=(int)($_GET['from']??$bid);
$items_stmt=db()->prepare('SELECT * FROM items WHERE tenant_id=? AND branch_id=? AND is_active=1 AND quantity>0 ORDER BY name');
$items_stmt->execute([$tid,$from_bid_sel]);$items=$items_stmt->fetchAll();
$today=date('Y-m-d');ob_start();
?>
<form method="post">
<div class="card">
  <div class="card-title"><span class="t-en">Transfer Details</span><span class="t-bn">ট্রান্সফার বিবরণ</span></div>
  <div class="form-grid">
    <div class="form-group">
      <label>From Branch</label>
      <select name="from_branch_id" onchange="this.form.submit()" id="from_b">
        <?php foreach($all_branches as $b): ?><option value="<?= $b['id'] ?>" <?= $b['id']==$from_bid_sel?'selected':'' ?>><?= h($b['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>To Branch</label>
      <select name="to_branch_id">
        <?php foreach($all_branches as $b): if($b['id']==$from_bid_sel)continue; ?><option value="<?= $b['id'] ?>"><?= h($b['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>Date</label><input type="date" name="date" value="<?= $today ?>" required></div>
  </div>
</div>
<div class="card">
  <div class="card-title">Items to Transfer</div>
  <div class="table-wrap"><table style="width:100%">
    <thead><tr><th>Item</th><th>Available</th><th>Transfer Qty</th></tr></thead>
    <tbody>
    <?php foreach($items as $it): ?>
      <tr>
        <td><?= h($it['name']) ?> <?= $it['sku']?'<small style="color:var(--c-muted)">['.h($it['sku']).']</small>':'' ?><input type="hidden" name="items[item_id][]" value="<?= $it['id'] ?>"><input type="hidden" name="items[description][]" value="<?= h($it['name']) ?>"></td>
        <td style="color:var(--c-income)"><?= $it['quantity']+0 ?> <?= h($it['unit']) ?></td>
        <td><input type="number" name="items[qty][]" step="0.001" min="0" max="<?= $it['quantity'] ?>" value="0" style="width:90px"></td>
      </tr>
    <?php endforeach; ?>
    <?php if(!$items): ?><tr><td colspan="3" style="text-align:center;padding:20px;color:var(--c-muted)">No items with stock in this branch</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<div class="card"><div class="form-group"><label>Notes</label><textarea name="notes" rows="2"></textarea></div></div>
<div style="display:flex;gap:10px"><button type="submit" class="btn btn-primary">🔄 Transfer Stock</button><a href="stock-transfers.php" class="btn btn-outline">Cancel</a></div>
</form>
<?php $content=ob_get_clean();include __DIR__.'/../templates/layout.php';
