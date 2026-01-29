<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Sample product data (replace with database query in production)
$products = [
  ["id" => 1, "name" => "សៀវភៅប្រលោមលោក", "price" => 10, "image" => "https://images.unsplash.com/photo-1544947950-fa07a98d45f6?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80", "category" => "សៀវភៅ"],
  ["id" => 2, "name" => "សៀវភៅវិទ្យាសាស្ត្រ", "price" => 15, "image" => "https://images.unsplash.com/photo-1589829085413-56de8f607c20?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80", "category" => "សៀវភៅ"],
  ["id" => 3, "name" => "សៀវភៅប្រវត្តិសាស្ត្រ", "price" => 12, "image" => "https://images.unsplash.com/photo-1512820790803-83ca734da794?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80", "category" => "សៀវភៅ"],
  ["id" => 20, "name" => "សៀវភៅអប់រំ", "price" => 18, "image" => "https://images.unsplash.com/photo-1516979187457-6376e7a9b567?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80", "category" => "សៀវភៅ"],
  ["id" => 21, "name" => "កាសស្តាប់ត្រចៀក", "price" => 20, "image" => "https://images.unsplash.com/photo-1505740420928-5e560c06d30e?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80", "category" => "អេឡិចត្រូនិក"],
  ["id" => 22, "name" => "ទូរស័ព្ទឆ្លាតវៃ", "price" => 300, "image" => "https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80", "category" => "អេឡិចត្រូនិក"],
  ["id" => 40, "name" => "ឧបករណ៍បញ្ជា", "price" => 50, "image" => "https://images.unsplash.com/photo-1600585154340-be6161a56a0c?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80", "category" => "អេឡិចត្រូនិក"],
  ["id" => 41, "name" => "អាវយឺត", "price" => 15, "image" => "https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80", "category" => "សម្លៀកបំពាក់"],
  ["id" => 60, "name" => "ស្បែកជើង", "price" => 35, "image" => "https://images.unsplash.com/photo-1542291026-7eec264c27ff?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80", "category" => "សម្លៀកបំពាក់"],
  ["id" => 61, "name" => "ក្រែមលាបមុខ", "price" => 12, "image" => "https://images.unsplash.com/photo-1596755094514-f87e34085b2c?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80", "category" => "គ្រឿងសម្អាង"],
  ["id" => 80, "name" => "លីបស្ទីក", "price" => 10, "image" => "https://images.unsplash.com/photo-1586495777744-4413f21062fa?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80", "category" => "គ្រឿងសម្អាង"],
  ["id" => 81, "name" => "ចានកែវ", "price" => 8, "image" => "https://images.unsplash.com/photo-1600585154340-be6161a56a0c?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80", "category" => "គ្រឿងប្រើប្រាស់ផ្ទះ"],
  ["id" => 100, "name" => "ខ្នើយ", "price" => 20, "image" => "https://images.unsplash.com/photo-1584100936595-7791b4577370?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80", "category" => "គ្រឿងប្រើប្រាស់ផ្ទះ"]
];

echo json_encode([
  "success" => true,
  "products" => $products
]);
?>