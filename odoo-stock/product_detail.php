<?php
session_start();
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

$url = 'https://www.skcosmetic.com/jsonrpc'; 
$db = 'skco';
$username = 'samann.oeun@sksk.asia';
$password = 'a03756141edaf598a0b7938224c62ae91ed966d3';

function callOdoo($url,$service,$method,$args){
    $payload=json_encode(['jsonrpc'=>'2.0','method'=>'call','params'=>['service'=>$service,'method'=>$method,'args'=>$args],'id'=>rand(0,1000000)]);
    $ch=curl_init($url);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_HTTPHEADER,['Content-Type: application/json']);
    curl_setopt($ch,CURLOPT_POST,true);
    curl_setopt($ch,CURLOPT_POSTFIELDS,$payload);
    $response=curl_exec($ch);
    if(!$response) die("cURL Error: ".curl_error($ch));
    curl_close($ch);
    $res=json_decode($response,true);
    if(isset($res['error'])) die("Odoo Error: ".json_encode($res['error']));
    return $res['result'];
}

if(!isset($_SESSION['uid'])){
    $_SESSION['uid']=callOdoo($url,'common','authenticate',[$db,$username,$password,[]]);
}
$uid=$_SESSION['uid'];

if(!isset($_GET['id'])) die("❌ Product ID missing!");
$id=intval($_GET['id']);

$product=callOdoo($url,'object','execute_kw',[$db,$uid,$password,'product.product','read',[[$id]],['fields'=>['id','display_name','default_code','list_price','image_1920','categ_id']]]);
if(empty($product)) die("❌ Product not found!");
$p=$product[0];
?>
<!DOCTYPE html>
<html lang="km">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?=htmlspecialchars($p['display_name'])?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#fff;font-family:system-ui;}
.img-wrap img{width:100%;border-radius:10px;}
.product-title{font-weight:700;font-size:1.3rem;margin-top:10px;}
.product-price{color:#28a745;font-weight:700;font-size:1.1rem;}
.btn-back{background:#0d6efd;color:#fff;border-radius:6px;padding:6px 12px;text-decoration:none;}
</style>
</head>
<body>
<div class="container py-3">
  <a href="index.php" class="btn-back mb-5">&larr; ត្រឡប់</a>
  <div class="img-wrap mb-3 mt-3">
    <?php $img = !empty($p['image_1920']) ? "data:image/png;base64,".$p['image_1920'] : "https://via.placeholder.com/400x300.png?text=No+Image"; ?>
    <img src="<?=$img?>" alt="<?=htmlspecialchars($p['display_name'])?>">
  </div>
  <h4 class="product-title"><?=htmlspecialchars($p['display_name'])?></h4>
  <p><b>Code:</b> <?=htmlspecialchars($p['default_code'])?></p>
  <p class="product-price">$<?=number_format($p['list_price'],2)?></p>
  <p><b>ប្រភេទ:</b> <?=isset($p['categ_id'][1])?$p['categ_id'][1]:'-'?></p>
  <p>Lorem ipsum description demo…</p>
 
</div>
</body>
</html>
