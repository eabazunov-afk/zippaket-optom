<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/catalog_functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$quick = isset($_GET['quick']);

if (!$id) {
    http_response_code(404);
    die('Товар не найден');
}

$catalog = new Catalog();
$product = $catalog->getProductById($id);

if (!$product) {
    http_response_code(404);
    die('Товар не найден');
}

if ($quick) {
    $price = number_format($product['price_rub'], 2, ',', ' ');
    $size = $product['width'] . '×' . $product['height'] . ' мм';
    ?>
        <div class="quick-view-product">
            <div class="quick-view-grid">
                <div class="quick-view-image">
                    <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['full_name']) ?>">
                </div>
                <div class="quick-view-info">
                    <h2><?= htmlspecialchars($product['full_name']) ?></h2>

                    <div class="quick-view-specs">
                        <div class="spec-row">
                            <span class="spec-label">Размер:</span>
                            <span class="spec-value"><?= $size ?></span>
                        </div>
                        <?php if ($product['thickness']): ?>
                        <div class="spec-row">
                            <span class="spec-label">Толщина:</span>
                            <span class="spec-value"><?= $product['thickness'] ?> мкм</span>
                        </div>
                        <?php endif; ?>
                        <?php if ($product['color']): ?>
                        <div class="spec-row">
                            <span class="spec-label">Цвет:</span>
                            <span class="spec-value"><?= htmlspecialchars($product['color']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="spec-row">
                            <span class="spec-label">Категория:</span>
                            <span class="spec-value"><?= htmlspecialchars($product['category']) ?></span>
                        </div>
                        <div class="spec-row">
                            <span class="spec-label">Класс:</span>
                            <span class="spec-value">
                                <span class="badge-abc <?= $product['abc_class'] ?>"><?= $product['abc_class'] ?></span>
                                <span class="badge-xyz <?= $product['xyz_class'] ?>"><?= $product['xyz_class'] ?></span>
                            </span>
                        </div>
                    </div>

                    <div class="quick-view-price">
                        <div class="price"><?= $price ?> ₽/шт</div>
                        <div class="stock">В наличии: <?= number_format($product['stock_quantity'], 0, ',', ' ') ?> шт</div>
                    </div>

                    <div class="quick-view-actions">
                        <button class="btn btn-primary add-to-cart"
                                data-id="<?= $product['id'] ?>"
                                data-name="<?= htmlspecialchars($product['full_name']) ?>"
                                data-price="<?= $product['price_rub'] ?>">
                            <i class="fas fa-shopping-cart"></i> Добавить в заявку
                        </button>
                        <a href="/product/<?= $product['id'] ?>" class="btn btn-outline">
                            <i class="fas fa-eye"></i> Подробнее
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
} else {
    // Полная карточка товара - для отдельной страницы
    // Здесь можно вывести полную информацию
}
