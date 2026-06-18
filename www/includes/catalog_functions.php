<?php
/**
 * Функции для работы с каталогом товаров
 */

require_once __DIR__ . '/config.php';

class Catalog {
    private $db;
    
    public function __construct() {
        // Подключение к БД через PDO (предполагается, что в config.php есть настройки)
        try {
            $this->db = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            error_log("Ошибка подключения к БД: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Получить все категории товаров
     */
    public function getCategories() {
        try {
            $stmt = $this->db->query("SELECT DISTINCT category FROM products WHERE is_active = 1 ORDER BY category");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("Ошибка получения категорий: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Получить все доступные толщины для фильтра
     */
    public function getThicknesses() {
        try {
            $stmt = $this->db->query("SELECT DISTINCT thickness FROM products WHERE thickness IS NOT NULL AND is_active = 1 ORDER BY thickness");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("Ошибка получения толщин: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Получить все доступные цвета
     */
    public function getColors() {
        try {
            $stmt = $this->db->query("SELECT DISTINCT color FROM products WHERE color IS NOT NULL AND color != '' AND is_active = 1 ORDER BY color");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("Ошибка получения цветов: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Получить один товар по id (только активные). null, если не найден.
     */
    public function getProductById(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM products WHERE id = :id AND is_active = 1");
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $product = $stmt->fetch();
            if (!$product) {
                return null;
            }

            $product['formatted_price'] = number_format($product['price_rub'], 2, ',', ' ') . ' ₽';
            $product['formatted_stock'] = number_format($product['stock_quantity'], 0, ',', ' ');
            $product['size_display'] = $product['width'] . '×' . $product['height'] . ' мм';
            $product['image_url'] = !empty($product['image_url']) ? $product['image_url'] : '/images/no-image.jpg';

            if (strpos($product['category'], 'слайдер') !== false) {
                $product['type_icon'] = 'sliders-h';
                $product['type_name'] = 'Слайдер';
            } else {
                $product['type_icon'] = 'lock';
                $product['type_name'] = 'ZIP-LOCK';
            }

            return $product;
        } catch (PDOException $e) {
            error_log("Ошибка получения товара по id: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Получить товары с фильтрацией и пагинацией
     */
    public function getProducts($filters = [], $page = 1, $perPage = 12) {
        try {
            $where = ["is_active = 1"];
            $params = [];
            
            // Фильтр по категории
            if (!empty($filters['category'])) {
                $where[] = "category = :category";
                $params[':category'] = $filters['category'];
            }
            
            // Фильтр по типу (слайдер или zip-lock)
            if (!empty($filters['type'])) {
                if ($filters['type'] === 'slider') {
                    $where[] = "category LIKE '%слайдер%'";
                } elseif ($filters['type'] === 'ziplock') {
                    $where[] = "category LIKE '%zip-lock%'";
                }
            }
            
            // Фильтр по толщине
            if (!empty($filters['thickness'])) {
                $where[] = "thickness = :thickness";
                $params[':thickness'] = $filters['thickness'];
            }
            
            // Фильтр по цвету
            if (!empty($filters['color'])) {
                $where[] = "color = :color";
                $params[':color'] = $filters['color'];
            }
            
            // Фильтр по наличию
            if (!empty($filters['in_stock']) && $filters['in_stock'] === 'yes') {
                $where[] = "stock_quantity > 0";
            }
            
            // Фильтр по минимальному размеру
            if (!empty($filters['min_width'])) {
                $where[] = "width >= :min_width";
                $params[':min_width'] = (int)$filters['min_width'];
            }
            
            if (!empty($filters['max_width'])) {
                $where[] = "width <= :max_width";
                $params[':max_width'] = (int)$filters['max_width'];
            }
            
            if (!empty($filters['min_height'])) {
                $where[] = "height >= :min_height";
                $params[':min_height'] = (int)$filters['min_height'];
            }
            
            if (!empty($filters['max_height'])) {
                $where[] = "height <= :max_height";
                $params[':max_height'] = (int)$filters['max_height'];
            }
            
            // Поиск по названию
            if (!empty($filters['search'])) {
                $where[] = "(full_name LIKE :search OR short_name LIKE :search)";
                $params[':search'] = '%' . $filters['search'] . '%';
            }
            
            // Сортировка
            $orderBy = "id DESC";
            if (!empty($filters['sort'])) {
                switch ($filters['sort']) {
                    case 'price_asc':
                        $orderBy = "price_rub ASC";
                        break;
                    case 'price_desc':
                        $orderBy = "price_rub DESC";
                        break;
                    case 'popular':
                        $orderBy = "quantity_sold DESC";
                        break;
                    case 'stock':
                        $orderBy = "stock_quantity DESC";
                        break;
                    default:
                        $orderBy = "id DESC";
                }
            }
            
            // Подсчет общего количества
            $countSql = "SELECT COUNT(*) FROM products WHERE " . implode(" AND ", $where);
            $countStmt = $this->db->prepare($countSql);
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $total = $countStmt->fetchColumn();
            
            // Пагинация
            $offset = ($page - 1) * $perPage;
            
            // Получение товаров
            $sql = "SELECT * FROM products WHERE " . implode(" AND ", $where) . " ORDER BY $orderBy LIMIT $offset, $perPage";
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $products = $stmt->fetchAll();
            
            // Форматирование данных для вывода
            foreach ($products as &$product) {
                $product['formatted_price'] = number_format($product['price_rub'], 2, ',', ' ') . ' ₽';
                $product['formatted_stock'] = number_format($product['stock_quantity'], 0, ',', ' ');
                $product['size_display'] = $product['width'] . '×' . $product['height'] . ' мм';
                $product['image_url'] = !empty($product['image_url']) ? $product['image_url'] : '/images/no-image.jpg';
                
                // Определяем тип для иконки
                if (strpos($product['category'], 'слайдер') !== false) {
                    $product['type_icon'] = 'sliders-h';
                    $product['type_name'] = 'Слайдер';
                } else {
                    $product['type_icon'] = 'lock';
                    $product['type_name'] = 'ZIP-LOCK';
                }
            }
            
            return [
                'products' => $products,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => ceil($total / $perPage)
            ];
            
        } catch (PDOException $e) {
            error_log("Ошибка получения товаров: " . $e->getMessage());
            return [
                'products' => [],
                'total' => 0,
                'page' => 1,
                'perPage' => $perPage,
                'totalPages' => 0
            ];
        }
    }
    
    /**
     * Получить товары по классу ABC/XYZ для аналитики
     */
    public function getProductsByClass($abc = null, $xyz = null, $limit = 10) {
        try {
            $where = ["is_active = 1"];
            $params = [];
            
            if ($abc) {
                $where[] = "abc_class = :abc";
                $params[':abc'] = $abc;
            }
            
            if ($xyz) {
                $where[] = "xyz_class = :xyz";
                $params[':xyz'] = $xyz;
            }
            
            $sql = "SELECT * FROM products WHERE " . implode(" AND ", $where) . " ORDER BY stock_quantity DESC LIMIT $limit";
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Ошибка получения товаров по классу: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Получить популярные товары
     */
    public function getPopularProducts($limit = 8) {
        try {
            $sql = "SELECT * FROM products WHERE is_active = 1 AND stock_quantity > 0 ORDER BY quantity_sold DESC, stock_quantity DESC LIMIT $limit";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Ошибка получения популярных товаров: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Получить товары со скидкой (с нулевым запасом или специальные предложения)
     */
    public function getSpecialOffers($limit = 6) {
        try {
            // Товары с большим остатком или специальные предложения
            $sql = "SELECT * FROM products WHERE is_active = 1 AND stock_quantity > 100000 ORDER BY stock_quantity DESC LIMIT $limit";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Ошибка получения спецпредложений: " . $e->getMessage());
            return [];
        }
    }
}