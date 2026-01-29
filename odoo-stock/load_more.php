<?php
session_start();
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);

$url='https://www.skcosmetic.com/jsonrpc';
$db='skco';
$username='samann.oeun@sksk.asia';
$password='a03756141edaf598a0b7938224c62ae91ed966d3';

function callOdoo($url,$service,$method,$args){
    $payload=json_encode(['jsonrpc'=>'2.0','method'=>'call','params'=>['service'=>$service,'method'=>$method,'args'=>$args],'id'=>rand(0,1000000)]);
    $ch=curl_init($url);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_HTTPHEADER,['Content-Type: application/json']);
    curl_setopt($ch,CURLOPT_POST,true);
    curl_setopt($ch,CURLOPT_POSTFIELDS,$payload);
    $res=curl_exec($ch);
    if(!$res) die("cURL Error: ".curl_error($ch));
    curl_close($ch);
    $res=json_decode($res,true);
    if(isset($res['error'])) die("Odoo Error: ".json_encode($res['error']));
    return $res['result'];
}

if(!isset($_SESSION['uid'])) $_SESSION['uid']=callOdoo($url,'common','authenticate',[$db,$username,$password,[]]);
$uid=$_SESSION['uid'];

$offset = intval($_POST['offset'] ?? 0);
$category = intval($_POST['category'] ?? 0);
$search = trim($_POST['search'] ?? '');

$domain = [['sale_ok','=',true]];
if($category) $domain[] = ['categ_id','=', $category];
if($search) $domain[] = ['display_name','ilike', $search];

$products = callOdoo($url,'object','execute_kw',[$db,$uid,$password,'product.product','search_read',[$domain],['fields'=>['id','display_name','list_price','image_1920','categ_id'],'limit'=>12,'offset'=>$offset]]);

if(empty($products)){ echo "no_more"; exit; }

foreach($products as $p){
    $img = !empty($p['image_1920']) ? "data:image/png;base64,".$p['image_1920'] : "https://via.placeholder.com/200x160.png?text=No+Image";
    echo '<div class="col-6 col-md-3 product-item" data-catid="'.($p['categ_id'][0]??0).'" data-name="'.htmlspecialchars(strtolower($p['display_name'])).'">
    <div class="product-card">
        <img src="'.$img.'">
        <div class="product-info">
            <div class="product-title">'.htmlspecialchars($p['display_name']).'</div>
            <div class="product-price">$'.number_format($p['list_price'],2).'</div>
            <a href="product_detail.php?id='.$p['id'].'" class="btn btn-detail w-100 mt-2">មើលព័ត៌មាន</a>
        </div>
    </div></div>';
}
?>
