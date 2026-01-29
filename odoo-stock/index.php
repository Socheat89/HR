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

// Authenticate
if(!isset($_SESSION['uid'])) $_SESSION['uid']=callOdoo($url,'common','authenticate',[$db,$username,$password,[]]);
$uid=$_SESSION['uid'];

// Get categories
$categories=callOdoo($url,'object','execute_kw',[$db,$uid,$password,'product.category','search_read',[[]],['fields'=>['id','name']]]);
?>
<!DOCTYPE html>
<html lang="km">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mini Ecommerce</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;700&display=swap" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<style>
body{font-family: 'Kantumruy Pro', 'Battambang', 'Noto Sans Khmer', sans-serif; }
.product-card{border:none;border-radius:12px;overflow:hidden;box-shadow:0 2px 6px rgba(0,0,0,0.08);transition:.2s;margin-bottom:15px;}
.product-card img{height:160px;object-fit:cover;width:100%;}
.product-info{padding:10px;}
.product-title{font-weight:600;font-size:1rem;margin-bottom:5px;}
.product-price{color:#28a745;font-weight:700;font-size:.95rem;}
.btn-detail{background:#0d6efd;color:#fff;border-radius:6px;padding:5px;font-size:.85rem;}

</style>
</head>
<body>
<div class="container py-3">
<h5 class="text-center mb-3">🛍 ផលិតផល</h5>
<div class="row mb-3 g-2">
<div class="col-6 col-md-4">
<input type="text" id="searchBox" class="form-control" placeholder="ស្វែងរកផលិតផល...">
</div>
<div class="col-6 col-md-4">
<select id="categoryFilter" class="form-select">
<option value="">ទាំងអស់</option>
<?php foreach($categories as $c){ ?>
<option value="<?=$c['id']?>"><?=htmlspecialchars($c['name'])?></option>
<?php } ?>
</select>
</div>
</div>

<div class="row g-3" id="productGrid">
<!-- Products loaded via AJAX -->
</div>

<div class="text-center mt-3">
<button class="btn btn-outline-primary" id="loadMore"><i class="bi bi-arrow-down-circle"></i> បង្ហាញបន្ថែម</button>
</div>
</div>

<script>
const tg=window.Telegram.WebApp; tg.expand();
let offset = 0;

// Load products function
function loadProducts(offset=0, category='', search='') {
    $.post("load_more.php", {offset: offset, category: category, search: search}, function(res){
        if(offset==0) $("#productGrid").html(res); // Replace for new search/filter
        else $("#productGrid").append(res); // Load more
        if(res.trim()==="no_more") $("#loadMore").prop("disabled",true).text("គ្មានទៀតទេ");
        else $("#loadMore").prop("disabled",false).text("បង្ហាញបន្ថែម");
    });
}

// Initial load
loadProducts();

// Load more button
$("#loadMore").click(function(){
    let cat=$("#categoryFilter").val();
    let search=$("#searchBox").val();
    offset += 12;
    loadProducts(offset, cat, search);
});

// Filter / Search event
$("#categoryFilter, #searchBox").on("change keyup", function(){
    offset = 0; // Reset offset for new filter/search
    let cat=$("#categoryFilter").val();
    let search=$("#searchBox").val();
    loadProducts(offset, cat, search);
});
</script>
</body>
</html>
