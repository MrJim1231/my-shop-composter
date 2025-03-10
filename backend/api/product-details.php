<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Подключаем файл с подключением к базе данных
require_once __DIR__ . '/../includes/db.php';

// Стартуем сессию для работы с корзиной
session_start();

// Получаем id товара из URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Получаем информацию о товаре
$query = "SELECT products.*, categories.name AS category_name, categories.parent_id, products.image 
          FROM products
          JOIN categories ON products.category_id = categories.id
          WHERE products.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $product = $result->fetch_assoc();
    $product_name = $conn->real_escape_string($product['name']);
    $parent_id = $product['parent_id'];

    // Получаем все товары с таким же именем и parent_id, включая group_id и image
    $sizes_query = "SELECT products.id, products.name, products.size, products.price, products.availability, products.quantity_in_stock, products.group_id, products.image
                    FROM products 
                    JOIN categories ON products.category_id = categories.id 
                    WHERE products.name = ? 
                    AND categories.parent_id = ?";
    $sizes_stmt = $conn->prepare($sizes_query);
    $sizes_stmt->bind_param('si', $product_name, $parent_id);
    $sizes_stmt->execute();
    $sizes_result = $sizes_stmt->get_result();

    $sizes = [];
    while ($row = $sizes_result->fetch_assoc()) {
        // Группируем товары по group_id
        $sizes[$row['group_id']][] = $row;
    }

    // Если запрос метода POST для добавления в корзину
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
        $productExists = false;
        
        foreach ($cart as &$item) {
            if ($item['id'] == $product['id']) {
                $item['quantity'] += 1;
                $productExists = true;
                break;
            }
        }
        
        if (!$productExists) {
            $product['quantity'] = 1;
            $cart[] = $product;
        }
        
        $_SESSION['cart'] = $cart;
        echo json_encode(["message" => "Product added to cart"]);
    } else {
        // Возвращаем товар, его размеры и изображение, включая group_id и name
        $product['sizes'] = $sizes;
        $product['name'] = $product_name;  // Добавляем название товара
        echo json_encode($product, JSON_UNESCAPED_UNICODE);
    }
} else {
    http_response_code(404);
    echo json_encode(["message" => "Product not found"]);
}
?>
