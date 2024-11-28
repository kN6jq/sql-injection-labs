<?php
// api.php
header('Content-Type: application/json');

// 数据库连接
try {
    $pdo = new PDO("mysql:host=localhost;dbname=security_lab", "root", "root");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die(json_encode(['error' => 'Connection failed: ' . $e->getMessage()]));
}

// 获取JSON请求数据
$input = file_get_contents('php://input');
$data = json_decode($input, true);
$action = $_GET['action'] ?? '';

switch($action) {
    // 1. 不安全的用户登录 - 简单参数
    case 'user_login':
        $username = $data['username'];
        $password = $data['password'];
        $sql = "SELECT * FROM users WHERE username='$username' AND password='$password'";
        $result = $pdo->query($sql);
        echo json_encode(['status' => $result->rowCount() > 0]);
        break;

    // 2. 安全的用户注册 - 复杂JSON对象
    case 'user_register':
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, phone) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $data['user']['credentials']['username'],
            password_hash($data['user']['credentials']['password'], PASSWORD_DEFAULT),
            $data['user']['contact']['email'],
            $data['user']['contact']['phone']
        ]);
        echo json_encode(['status' => true]);
        break;

    // 3. 不安全的商品搜索 - 复杂查询条件
    case 'search_products':
        $category = $data['filters']['category'];
        $price_range = $data['filters']['price_range'];
        $sort = $data['sort']['field'];
        $sql = "SELECT * FROM products WHERE category='$category' 
                AND price BETWEEN $price_range[0] AND $price_range[1] 
                ORDER BY $sort";
        $result = $pdo->query($sql);
        echo json_encode(['products' => $result->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // 4. 安全的订单创建 - 复杂JSON数组
    case 'create_order':
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, status) VALUES (?, ?, ?)");
        $total = array_sum(array_column($data['items'], 'price'));
        $stmt->execute([$data['user_id'], $total, 'pending']);
        echo json_encode(['status' => true]);
        break;

    // 5. 不安全的用户评论查询 - 简单参数
    case 'get_comments':
        $product_id = $data['product_id'];
        $sql = "SELECT * FROM comments WHERE product_id = $product_id";
        $result = $pdo->query($sql);
        echo json_encode(['comments' => $result->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // 6. 安全的用户资料更新 - 嵌套JSON对象
    case 'update_profile':
        $stmt = $pdo->prepare("UPDATE users SET email = ?, phone = ? WHERE id = ?");
        $stmt->execute([
            $data['profile']['contact']['email'],
            $data['profile']['contact']['phone'],
            $data['profile']['user_id']
        ]);
        echo json_encode(['status' => true]);
        break;

    // 7. 不安全的订单历史查询 - 日期范围数组
    case 'order_history':
        $user_id = $data['user_id'];
        $date_range = $data['date_range'];
        $sql = "SELECT * FROM orders WHERE user_id = $user_id 
                AND created_at BETWEEN '$date_range[0]' AND '$date_range[1]'";
        $result = $pdo->query($sql);
        echo json_encode(['orders' => $result->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // 8. 不安全的批量商品查询 - 数组参数
    case 'batch_products':
        $ids = implode(',', $data['product_ids']);
        $sql = "SELECT * FROM products WHERE id IN ($ids)";
        $result = $pdo->query($sql);
        echo json_encode(['products' => $result->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // 9. 安全的购物车更新 - 复杂操作数组
    case 'update_cart':
        $stmt = $pdo->prepare("INSERT INTO cart_items (user_id, product_id, quantity) 
                              VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = ?");
        foreach($data['cart_items'] as $item) {
            $stmt->execute([
                $data['user_id'],
                $item['product_id'],
                $item['quantity'],
                $item['quantity']
            ]);
        }
        echo json_encode(['status' => true]);
        break;

    // 10. 不安全的优惠券验证 - 简单参数
    case 'verify_coupon':
        $code = $data['code'];
        $sql = "SELECT * FROM coupons WHERE code = '$code' AND valid_until > NOW()";
        $result = $pdo->query($sql);
        echo json_encode(['valid' => $result->rowCount() > 0]);
        break;

    // 11. 不安全的用户搜索 - 多条件查询
    case 'search_users':
        $email = $data['filters']['email'];
        $status = $data['filters']['status'];
        $sql = "SELECT * FROM users WHERE email LIKE '%$email%' AND status = '$status'";
        $result = $pdo->query($sql);
        echo json_encode(['users' => $result->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // 12. 安全的订单状态更新 - 状态流转对象
    case 'update_order_status':
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ? AND user_id = ?");
        $stmt->execute([
            $data['status']['new_status'],
            $data['order_id'],
            $data['user_id']
        ]);
        echo json_encode(['status' => true]);
        break;

    // 13. 不安全的商品评分统计 - 聚合查询
    case 'product_ratings':
        $category = $data['category'];
        $min_rating = $data['min_rating'];
        $sql = "SELECT p.*, AVG(r.rating) as avg_rating 
                FROM products p 
                LEFT JOIN ratings r ON p.id = r.product_id 
                WHERE p.category = '$category' 
                GROUP BY p.id 
                HAVING avg_rating >= $min_rating";
        $result = $pdo->query($sql);
        echo json_encode(['ratings' => $result->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // 14. 安全的用户权限验证 - 角色检查对象
    case 'check_permission':
        $stmt = $pdo->prepare("SELECT permissions FROM user_roles 
                              WHERE user_id = ? AND role_id = ?");
        $stmt->execute([
            $data['auth']['user_id'],
            $data['auth']['role_id']
        ]);
        echo json_encode(['has_permission' => $stmt->rowCount() > 0]);
        break;

    // 15. 不安全的交易记录查询 - 复杂过滤条件
    case 'transaction_history':
        $user_id = $data['user_id'];
        $type = $data['filters']['type'];
        $status = $data['filters']['status'];
        $amount_range = $data['filters']['amount_range'];
        
        $sql = "SELECT * FROM transactions 
                WHERE user_id = $user_id 
                AND type = '$type' 
                AND status = '$status' 
                AND amount BETWEEN $amount_range[0] AND $amount_range[1]";
        $result = $pdo->query($sql);
        echo json_encode(['transactions' => $result->fetchAll(PDO::FETCH_ASSOC)]);
        break;
}