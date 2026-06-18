<?php

require_once 'init.php';

class Calculator {
    private $db;
    
    // Стоимость материалов за кг (уточненные цены)
    private $materialPrices = [
        'EVA' => 380,   // руб/кг для EVA (матовый)
        'ПВД' => 360    // руб/кг для ПВД (прозрачный)
    ];
    
    // Константы из формулы
    private $zipperWeightPerMeter = 6.4; // вес молнии на 1 метр (г/м)
    private $sliderWeight = 0.25;        // вес бегунка (г)
    private $filmDensity = 0.92;         // плотность пленки (г/м² на 1 мкм)
    
    public function __construct() {
        $this->db = getDbConnection();
    }
    
    /**
     * Основная формула расчета стоимости
     * ТОЛЬКО стоимость материала + скидки за объем
     */
    public function calculatePrice($type, $width, $height, $thickness, $material = null, $quantity = 1000) {
 
// Добавляем логирование
    error_log("CALCULATION REQUEST: type=$type, width=$width, height=$height, thickness=$thickness, material=$material, quantity=$quantity");

        // Если материал не указан, используем ПВД по умолчанию
        if ($material === null) {
            $material = 'ПВД';
        }
        
        // 1. Конвертируем размеры из см в метры
        $width_m = $width / 100;   // ширина в метрах
        $height_m = $height / 100; // высота в метрах
        
        // 2. Рассчитываем площадь пленки (две стороны пакета)
        $film_area = $width_m * $height_m * 2; // м²
        
        // 3. Рассчитываем вес пленки
        $film_weight = $film_area * $thickness * $this->filmDensity; // граммы
        
        // 4. Вес молнии
        $zipper_weight = $this->zipperWeightPerMeter * $width_m; // граммы
        
        // 5. Вес бегунка (только для слайдера)
        $slider_weight = ($type === 'slider') ? $this->sliderWeight : 0; // граммы
        
        // 6. Общий вес пакета в граммах
        $total_weight_grams = $film_weight + $zipper_weight + $slider_weight;
        
        // 7. Конвертируем в килограммы
        $total_weight_kg = $total_weight_grams / 1000;
        
        // 8. Стоимость материала за пакет (ЭТО И ЕСТЬ БАЗОВАЯ ЦЕНА)
        $material_price_per_kg = $this->materialPrices[$material] ?? 380;
        $base_price = $total_weight_kg * $material_price_per_kg;
        
        // 9. Применяем скидку за объем
        $discount = $this->getDiscountByQuantity($quantity);
        $unit_price = $base_price * (1 - $discount / 100);
        
        // 10. Общая стоимость заказа
        $total_price = $unit_price * $quantity;
        

    // В конце возвращаем результат с quantity из запроса
    return [
        'success' => true,
        'unit_price' => round($unit_price, 2),
        'total_price' => round($total_price, 2),
        'currency' => '₽',
        'quantity' => $quantity, // ВАЖНО: возвращаем то же quantity, что получили
        'quantity_tier' => $this->getQuantityTier($quantity),
        'discount_percent' => $discount,
        'base_price' => round($base_price, 2),
        'weight_grams' => round($total_weight_grams, 2),
        'weight_kg' => round($total_weight_kg, 4),
        'cost_breakdown' => [
            'base_price' => round($base_price, 2),
            'discount_percent' => $discount
        ],
        'calculation_details' => [
            'film_area' => round($film_area, 4),
            'film_weight' => round($film_weight, 2),
            'zipper_weight' => round($zipper_weight, 2),
            'slider_weight' => round($slider_weight, 2),
            'material_price_per_kg' => $material_price_per_kg
        ]
    ];
}


    /**
     * Скидка в зависимости от тиража
     */
    private function getDiscountByQuantity($quantity) {
        if ($quantity >= 30000) {
            return 30;    // 30% скидка
        } elseif ($quantity >= 20000) {
            return 20;    // 20% скидка
        } elseif ($quantity >= 10000) {
            return 15;    // 15% скидка
        } else {
            return 0;     // без скидки
        }
    }
    
    /**
     * Определить уровень цены по количеству
     */
    private function getQuantityTier($quantity) {
        if ($quantity >= 30000) {
            return 'opt30k';
        } elseif ($quantity >= 20000) {
            return 'opt20k';
        } elseif ($quantity >= 10000) {
            return 'opt10k';
        } else {
            return 'small';
        }
    }
    
/**
 * Получить доступные опции
 */
public function getAvailableOptions() {
    return [
        'types' => [
            'slider' => 'Пакет слайдер с бегунком',
            'ziplock' => 'Пакет с замком zip-lock (грипперы)'
        ],
        'widths' => range(4, 100, 1),
        'heights' => range(6, 100, 1),
        'thickness' => [35, 40, 50, 60, 70, 80, 90, 100, 120],
        'materials' => [
            'EVA' => 'EVA (матовый)',
            'ПВД' => 'ПВД (прозрачный)'
        ],
        'quantities' => [
            1000 => '1,000 шт',
            3000 => '3,000 шт',
            5000 => '5,000 шт',
            10000 => '10,000 шт (-15%)',
            15000 => '15,000 шт (-15%)',
            20000 => '20,000 шт (-20%)',
            25000 => '25,000 шт (-20%)',
            30000 => '30,000 шт (-30%)',
            40000 => '40,000 шт (-30%)',
            50000 => '50,000 шт (-30%)',
            100000 => '100,000 шт (-30%)',
            200000 => '200,000 шт (-30%)',
            300000 => '300,000 шт (-30%)',
            500000 => '500,000 шт (-30%)',
            1000000 => '1,000,000 шт (-30%)'
        ]
    ];
}
    
    /**
     * Проверить расчет на примере (для тестирования)
     */
    public function testCalculation($width = 25, $height = 30, $thickness = 60) {
        // Пример расчета для проверки
        $width_m = $width / 100;   // 0.25 м
        $height_m = $height / 100; // 0.30 м
        
        $film_area = $width_m * $height_m * 2; // 0.25 * 0.30 * 2 = 0.15 м²
        
        $film_weight = $film_area * $thickness * $this->filmDensity; // 0.15 * 60 * 0.92 = 8.28 г
        
        $zipper_weight = $this->zipperWeightPerMeter * $width_m; // 6.4 * 0.25 = 1.6 г
        
        $slider_weight = $this->sliderWeight; // 0.25 г
        
        $total_weight = $film_weight + $zipper_weight + $slider_weight; // 8.28 + 1.6 + 0.25 = 10.13 г
        
        $total_weight_kg = $total_weight / 1000; // 0.01013 кг
        
        $base_price = $total_weight_kg * 380; // 0.01013 * 380 = 3.85 ₽
        
        // Для 20,000 шт
        $discount = 20; // -20%
        $unit_price = $base_price * (1 - $discount/100); // 3.85 * 0.80 = 3.08 ₽
        
        return [
            'width_m' => $width_m,
            'height_m' => $height_m,
            'film_area' => $film_area,
            'film_weight' => $film_weight,
            'zipper_weight' => $zipper_weight,
            'slider_weight' => $slider_weight,
            'total_weight' => $total_weight,
            'total_weight_kg' => $total_weight_kg,
            'base_price' => $base_price,
            'discount_20k' => $discount,
            'unit_price_20k' => $unit_price
        ];
    }
}


/**
 * Функция для отображения формы калькулятора (с обновлениями)
 */
function displayCalculatorForm() {
    $calculator = new Calculator();
    $options = $calculator->getAvailableOptions();
    
    ob_start();
    ?>
    <div class="calculator" id="zipCalculator">
        <div class="calculator-header">
            <h2 class="section-title">Калькулятор стоимости</h2>
            <p class="section-subtitle">Расчёт и КП в течение 15 минут!</p>
        </div>
        
        <div class="calculator-body">
            <div class="calculator-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="type">
                            <i class="fas fa-box"></i>
                            Тип пакета
                        </label>
                        <select id="type" name="type" class="form-control">
                            <?php foreach ($options['types'] as $value => $label): ?>
                                <option value="<?php echo $value; ?>">
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="material" id="materialLabel">
                            <i class="fas fa-layer-group"></i>
                            Материал
                        </label>
                        <select id="material" name="material" class="form-control">
                            <?php foreach ($options['materials'] as $value => $label): ?>
                                <option value="<?php echo $value; ?>">
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="width">
                            <i class="fas fa-arrows-alt-h"></i>
                            Ширина (см)
                        </label>
                        <div class="input-with-unit">
                            <input type="number" 
                                   id="width" 
                                   name="width" 
                                   min="<?php echo min($options['widths']); ?>" 
                                   max="<?php echo max($options['widths']); ?>" 
                                   step="1" 
                                   value="25"
                                   class="form-control">
                            <span class="input-unit">см</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="height">
                            <i class="fas fa-arrows-alt-v"></i>
                            Высота (см)
                        </label>
                        <div class="input-with-unit">
                            <input type="number" 
                                   id="height" 
                                   name="height" 
                                   min="<?php echo min($options['heights']); ?>" 
                                   max="<?php echo max($options['heights']); ?>" 
                                   step="1" 
                                   value="30"
                                   class="form-control">
                            <span class="input-unit">см</span>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="thickness">
                            <i class="fas fa-ruler"></i>
                            Толщина (мкм)
                        </label>
                        <select id="thickness" name="thickness" class="form-control">
                            <?php foreach ($options['thickness'] as $thickness): ?>
                                <option value="<?php echo $thickness; ?>" <?php echo $thickness == 60 ? 'selected' : ''; ?>>
                                    <?php echo $thickness; ?> мкм
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    



<div class="form-group">
    <label for="quantity">
        <i class="fas fa-boxes"></i>
        Тираж (шт)
    </label>
    <select id="quantity" name="quantity" class="form-control">
        <?php foreach ($options['quantities'] as $value => $label): ?>
            <option value="<?php echo $value; ?>" <?php echo $value == 100 ? 'selected' : ''; ?>>
                <?php echo $label; ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

                </div>
            </div>
            
           









<div class="calculator-results" id="calculatorResults">
    <div class="results-header">
        <h3><i class="fas fa-calculator"></i> Результат расчёта</h3>
        <div class="size-info" id="sizeInfo">25 × 30 см</div>
    </div>
    
    <div class="results-body">
        <div class="result-item weight-info">
            <div class="result-label">
                <i class="fas fa-weight"></i>
                Вес пакета:
            </div>
            <div class="result-value" id="weightInfo">— г</div>
        </div>
        
      
        <div class="result-item discount-info">
            <div class="result-label">
                <i class="fas fa-tag"></i>
                Скидка за объем:
            </div>
            <div class="result-value" id="discountInfo">—</div>
        </div>
        
        <div class="result-item final-price">
            <div class="result-label">
                <i class="fas fa-check-circle"></i>
                <strong>Итоговая цена:</strong>
            </div>
            <div class="result-value" id="unitPrice">— ₽/шт</div>
        </div>
        
        <div class="result-item">
            <div class="result-label">Общая стоимость:</div>
            <div class="result-value" id="totalPrice">— ₽</div>
        </div>
        
        <div class="result-item">
            <div class="result-label">Тираж:</div>
            <div class="result-value" id="quantityDisplay">— шт</div>
        </div>
        
        <!-- Детализация расчета веса -->
       <!--<div class="weight-breakdown" id="weightBreakdown" style="display: none;">
            <div class="breakdown-header">
                <i class="fas fa-balance-scale"></i>
                <span>Детализация веса</span>
            </div>
            <div class="breakdown-item">
                <span>Вес пленки:</span>
                <span id="filmWeight">— г</span>
            </div>
            <div class="breakdown-item">
                <span>Вес молнии:</span>
                <span id="zipperWeight">— г</span>
            </div>
            <div class="breakdown-item">
                <span>Вес бегунка:</span>
                <span id="sliderWeight">— г</span>
            </div>
            <div class="breakdown-item total">
                <span>Общий вес:</span>
                <span id="totalWeightDetail">— г</span>
            </div>
        </div>-->
        
        <!-- Детализация стоимости -->
        <!--<div class="cost-breakdown" id="costBreakdown" style="display: none;">
            <div class="breakdown-header">
                <i class="fas fa-ruble-sign"></i>
                <span>Детализация цены</span>
            </div>
            <div class="breakdown-item">
                <span>Вес пакета:</span>
                <span id="weightDetail">— кг</span>
            </div>
            <div class="breakdown-item">
                <span>Цена пленки:</span>
                <span id="materialPrice">— ₽/кг</span>
            </div>
            <div class="breakdown-item">
                <span>Базовая цена:</span>
                <span id="basePriceDetail">— ₽</span>
            </div>
            <div class="breakdown-item discount">
                <span>Скидка за объем:</span>
                <span id="discountPercent">— %</span>
            </div>
        </div>-->
        
        <!--<div class="result-note" id="resultNote">
            <i class="fas fa-info-circle"></i>
            <span>Расчёт по формуле: (Площадь × Толщина × 0,92 + Молния + Бегунок) × Цена пленки</span>
        </div>-->
    </div>
</div>
                
                <div class="results-footer">
                    <button class="btn btn-primary btn-block" id="saveCalculation">
                        <i class="fas fa-save"></i>
                        Сохранить расчёт
                    </button>
                    
                    <button class="btn btn-outline btn-block" id="requestOffer">
                        <i class="fas fa-envelope"></i>
                        Запросить КП
                    </button>
                    
                    <button class="btn btn-outline btn-block" id="printCalculation">
                        <i class="fas fa-print"></i>
                        Распечатать
                    </button>
                    
                    <button class="btn btn-link btn-block" id="toggleBreakdown">
                        <i class="fas fa-eye"></i>
                        Показать детали
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Отдельный скрипт для калькулятора
  class ZipCalculatorJS {
    constructor() {
        this.type = 'slider';
        this.width = 25;
        this.height = 30;
        this.thickness = 60;
        this.material = 'EVA';
        this.quantity = 100; // ИЗМЕНЕНО: было 20000
        this.isInitialized = false;
    }
    
    init() {
        if (this.isInitialized) return;
        
        this.typeSelect = document.getElementById('type');
        this.materialSelect = document.getElementById('material');
        this.materialLabel = document.getElementById('materialLabel');
        this.widthInput = document.getElementById('width');
        this.heightInput = document.getElementById('height');
        this.thicknessSelect = document.getElementById('thickness');
        this.quantitySelect = document.getElementById('quantity');
        
        // Получаем элементы для отображения результатов
        this.unitPriceElement = document.getElementById('unitPrice');
        this.totalPriceElement = document.getElementById('totalPrice');
        this.quantityDisplayElement = document.getElementById('quantityDisplay');
        this.basePriceElement = document.getElementById('basePrice');
        this.discountInfoElement = document.getElementById('discountInfo');
        this.weightInfoElement = document.getElementById('weightInfo');
        this.resultNoteElement = document.getElementById('resultNote');
        this.sizeInfoElement = document.getElementById('sizeInfo');
        
        this.saveBtn = document.getElementById('saveCalculation');
        this.requestBtn = document.getElementById('requestOffer');
        this.printBtn = document.getElementById('printCalculation');
        this.toggleBreakdownBtn = document.getElementById('toggleBreakdown');
        this.costBreakdownElement = document.getElementById('costBreakdown');
        
        if (!this.typeSelect) {
            console.error('Элемент калькулятора не найден');
            return;
        }
        
        // СИНХРОНИЗИРУЕМ: берем значение из select, а не устанавливаем свое
        if (this.quantitySelect) {
            this.quantity = parseInt(this.quantitySelect.value) || 100;
            console.log('Initial quantity from select:', this.quantitySelect.value, 'parsed:', this.quantity);
        }
        
        this.bindEvents();
        this.updateSizeInfo();
        this.calculate(); // Вызываем расчет с правильным quantity
        
        this.isInitialized = true;
        console.log('Калькулятор инициализирован. Quantity =', this.quantity);
    }
    
    bindEvents() {
        this.typeSelect.addEventListener('change', () => {
            this.type = this.typeSelect.value;
            this.calculate();
        });
        
        if (this.materialSelect) {
            this.materialSelect.addEventListener('change', () => {
                this.material = this.materialSelect.value;
                this.calculate();
            });
        }
        
        if (this.widthInput) {
            this.widthInput.addEventListener('change', () => {
                this.width = parseInt(this.widthInput.value) || 25;
                this.updateSizeInfo();
                this.calculate();
            });
            
            this.widthInput.addEventListener('input', () => {
                this.updateSizeInfo();
            });
        }
        
        if (this.heightInput) {
            this.heightInput.addEventListener('change', () => {
                this.height = parseInt(this.heightInput.value) || 30;
                this.updateSizeInfo();
                this.calculate();
            });
            
            this.heightInput.addEventListener('input', () => {
                this.updateSizeInfo();
            });
        }
        
        if (this.thicknessSelect) {
            this.thicknessSelect.addEventListener('change', () => {
                this.thickness = parseInt(this.thicknessSelect.value) || 60;
                this.calculate();
            });
        }
        
        // ИСПРАВЛЕНИЕ: правильно обрабатываем изменение quantity
        if (this.quantitySelect) {
            this.quantitySelect.addEventListener('change', () => {
                this.quantity = parseInt(this.quantitySelect.value) || 100;
                console.log('Quantity changed to:', this.quantity);
                this.calculate();
            });
        }
        
        if (this.saveBtn) {
            this.saveBtn.addEventListener('click', () => this.saveCalculation());
        }
        
        if (this.requestBtn) {
            this.requestBtn.addEventListener('click', () => this.requestOffer());
        }
        
        if (this.printBtn) {
            this.printBtn.addEventListener('click', () => this.printCalculation());
        }
        
        if (this.toggleBreakdownBtn) {
            this.toggleBreakdownBtn.addEventListener('click', () => {
                if (this.costBreakdownElement.style.display === 'none') {
                    this.costBreakdownElement.style.display = 'block';
                    this.toggleBreakdownBtn.innerHTML = '<i class="fas fa-eye-slash"></i> Скрыть детали';
                } else {
                    this.costBreakdownElement.style.display = 'none';
                    this.toggleBreakdownBtn.innerHTML = '<i class="fas fa-eye"></i> Показать детали';
                }
            });
        }
    }
    
    updateSizeInfo() {
        if (!this.sizeInfoElement) return;
        const width = this.widthInput ? (this.widthInput.value || this.width) : this.width;
        const height = this.heightInput ? (this.heightInput.value || this.height) : this.height;
        this.sizeInfoElement.textContent = `${width} × ${height} см`;
    }
    
    calculate() {
        if (!this.unitPriceElement) return;
        
        // Показываем индикатор загрузки
        this.showLoading();
        
        const data = {
            type: this.type,
            width: this.width,
            height: this.height,
            thickness: this.thickness,
            material: this.material,
            quantity: this.quantity
        };
        
        console.log('Sending calculation request:', data);
        
        // Используем fetch для отправки данных на сервер
        fetch('/includes/api.php?action=calculate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            console.log('Calculation response:', result);
            if (result.success && result.data) {
                this.updateResults(result.data);
            } else {
                this.showError(result.message || 'Ошибка при расчёте');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            this.showError('Ошибка при подключении к серверу');
        });
    }
    
    updateResults(data) {
        console.log('Updating results with data:', data);
        
        // Форматируем цены
        const basePriceFormatted = new Intl.NumberFormat('ru-RU', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(data.base_price || 0);
        
        const unitPriceFormatted = new Intl.NumberFormat('ru-RU', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(data.unit_price || 0);
        
        const totalPriceFormatted = new Intl.NumberFormat('ru-RU', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(data.total_price || 0);
        
        const quantityFormatted = new Intl.NumberFormat('ru-RU').format(data.quantity || this.quantity);
        
        // Обновляем UI
        if (this.basePriceElement) {
            this.basePriceElement.textContent = basePriceFormatted + ' ₽/шт';
            console.log('Base price updated:', basePriceFormatted);
        }
        
        if (this.unitPriceElement) {
            this.unitPriceElement.textContent = unitPriceFormatted + ' ₽/шт';
            console.log('Unit price updated:', unitPriceFormatted);
        }
        
        if (this.totalPriceElement) {
            this.totalPriceElement.textContent = totalPriceFormatted + ' ₽';
            console.log('Total price updated:', totalPriceFormatted);
        }
        
        if (this.quantityDisplayElement) {
            this.quantityDisplayElement.textContent = quantityFormatted + ' шт';
            console.log('Quantity display updated:', quantityFormatted);
        }
        
        // Проверяем соответствие quantity
        if (data.quantity !== this.quantity) {
            console.warn('Warning: Server quantity', data.quantity, 'differs from client quantity', this.quantity);
        }
        
       // Обновляем вес пакета (только граммы)
if (this.weightInfoElement) {
    const weightFormatted = new Intl.NumberFormat('ru-RU', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1
    }).format(data.weight_grams || 0);
    
    this.weightInfoElement.textContent = weightFormatted + ' г';
}
        
        // Обновляем информацию о скидке
        if (this.discountInfoElement) {
            const discount = data.discount_percent || 0;
            if (discount > 0) {
                const saved = (data.base_price * discount / 100).toFixed(2);
                this.discountInfoElement.innerHTML = `
                    <span class="discount-badge">-${discount}%</span>
                    <div class="discount-details">
                        <small>Экономия: ${saved} ₽/шт</small>
                    </div>
                `;
                this.discountInfoElement.className = 'result-value has-discount';
            } else {
                this.discountInfoElement.textContent = 'Нет скидки';
                this.discountInfoElement.className = 'result-value';
            }
        }
        
        // Обновляем детализацию веса
        if (data.calculation_details) {
            const details = data.calculation_details;
            const weightBreakdownElement = document.getElementById('weightBreakdown');
            const filmWeightElement = document.getElementById('filmWeight');
            const zipperWeightElement = document.getElementById('zipperWeight');
            const sliderWeightElement = document.getElementById('sliderWeight');
            const totalWeightElement = document.getElementById('totalWeightDetail');
            
            if (weightBreakdownElement) weightBreakdownElement.style.display = 'block';
            if (filmWeightElement) {
                filmWeightElement.textContent = new Intl.NumberFormat('ru-RU', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }).format(details.film_weight) + ' г';
            }
            if (zipperWeightElement) {
                zipperWeightElement.textContent = new Intl.NumberFormat('ru-RU', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }).format(details.zipper_weight) + ' г';
            }
            if (sliderWeightElement) {
                sliderWeightElement.textContent = new Intl.NumberFormat('ru-RU', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }).format(details.slider_weight) + ' г';
            }
            if (totalWeightElement) {
                totalWeightElement.textContent = data.weight_grams + ' г';
            }
        }
        
        // Обновляем детализацию стоимости
        if (data.calculation_details && data.cost_breakdown) {
            const details = data.calculation_details;
            const breakdown = data.cost_breakdown;
            const costBreakdownElement = document.getElementById('costBreakdown');
            const weightDetailElement = document.getElementById('weightDetail');
            const materialPriceElement = document.getElementById('materialPrice');
            const basePriceDetailElement = document.getElementById('basePriceDetail');
            const discountPercentElement = document.getElementById('discountPercent');
            
            if (costBreakdownElement) costBreakdownElement.style.display = 'block';
            if (weightDetailElement) {
                weightDetailElement.textContent = data.weight_kg + ' кг';
            }
            if (materialPriceElement) {
                materialPriceElement.textContent = details.material_price_per_kg + ' ₽/кг';
            }
            if (basePriceDetailElement) {
                basePriceDetailElement.textContent = breakdown.base_price + ' ₽';
            }
            if (discountPercentElement) {
                discountPercentElement.textContent = breakdown.discount_percent + '%';
            }
        }
        
        // Обновляем примечание
        if (this.resultNoteElement && data.calculation_details) {
            const materialPrice = data.calculation_details.material_price_per_kg;
            this.resultNoteElement.innerHTML = `
                <i class="fas fa-info-circle"></i>
                <span>Цена материала: ${materialPrice} ₽/кг. Скидки: от 10к шт -15%, от 20к шт -20%, от 30к шт -30%</span>
            `;
        }
    }
    
    showLoading() {
        if (this.basePriceElement) this.basePriceElement.textContent = '... ₽/шт';
        if (this.unitPriceElement) this.unitPriceElement.textContent = '... ₽/шт';
        if (this.totalPriceElement) this.totalPriceElement.textContent = '... ₽';
        if (this.quantityDisplayElement) this.quantityDisplayElement.textContent = '... шт';
        if (this.weightInfoElement) this.weightInfoElement.textContent = '... г';
        if (this.discountInfoElement) this.discountInfoElement.textContent = '...';
    }
    
    showError(message) {
        if (this.basePriceElement) this.basePriceElement.textContent = '— ₽/шт';
        if (this.unitPriceElement) this.unitPriceElement.textContent = '— ₽/шт';
        if (this.totalPriceElement) this.totalPriceElement.textContent = '— ₽';
        if (this.quantityDisplayElement) this.quantityDisplayElement.textContent = '— шт';
        if (this.weightInfoElement) this.weightInfoElement.textContent = '— г';
        if (this.discountInfoElement) this.discountInfoElement.textContent = 'Ошибка';
        
        if (this.resultNoteElement) {
            this.resultNoteElement.innerHTML = `
                <i class="fas fa-exclamation-circle"></i>
                <span>${message}</span>
            `;
        }
    }

       
        saveCalculation() {
            const data = {
                type: this.type,
                width: this.width,
                height: this.height,
                thickness: this.thickness,
                material: this.material,
                quantity: this.quantity
            };
            
            fetch('/includes/api.php?action=save_calculation', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showNotification(`Расчёт сохранен! ID: ${result.calculation_id}`, 'success');
                } else {
                    showNotification(result.message || 'Ошибка при сохранении', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Ошибка при подключении к серверу', 'error');
            });
        }
        


requestOffer() {
    const modal = document.createElement('div');
    modal.className = 'modal active';
    modal.innerHTML = `
        <div class="modal-content">
            <button class="modal-close"><i class="fas fa-times"></i></button>
            <h3>Запросить коммерческое предложение</h3>
            <form id="offerForm">
                <div class="form-group">
                    <input type="text" name="name" placeholder="Ваше имя *" required>
                </div>
                <div class="form-group">
                    <input type="tel" name="phone" placeholder="Телефон *" required>
                </div>
                <div class="form-group">
                    <input type="email" name="email" placeholder="Email">
                </div>
                <div class="form-group">
                    <textarea name="message" placeholder="Дополнительные пожелания..." rows="3"></textarea>
                </div>
                <div class="form-group">
                    <div class="calculation-summary">
                        <p><strong>Параметры заказа:</strong></p>
                        <p id="summaryType">Тип: ${this.typeSelect.options[this.typeSelect.selectedIndex].text}</p>
                        <p id="summarySize">Размер: ${this.width}×${this.height} см</p>
                        <p id="summaryThickness">Толщина: ${this.thickness} мкм</p>
                        <p id="summaryMaterial">Материал: ${this.materialSelect.options[this.materialSelect.selectedIndex].text}</p>
                        <p id="summaryQuantity">Тираж: ${new Intl.NumberFormat('ru-RU').format(this.quantity)} шт</p>
                        <p id="summaryPrice">Примерная стоимость: ${this.totalPriceElement ? this.totalPriceElement.textContent : '— ₽'}</p>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Отправить запрос
                </button>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    const closeBtn = modal.querySelector('.modal-close');
    closeBtn.addEventListener('click', () => modal.remove());
    
    const form = modal.querySelector('#offerForm');
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        // Формируем параметры для передачи в AmoCRM в правильном формате
        const parameters = {
            calculation_data: {
                type: this.typeSelect.options[this.typeSelect.selectedIndex].text,
                size: `${this.width}×${this.height} см`,
                thickness: `${this.thickness} мкм`,
                material: this.materialSelect.options[this.materialSelect.selectedIndex].text,
                quantity: `${new Intl.NumberFormat('ru-RU').format(this.quantity)} шт`,
                estimated_price: this.totalPriceElement ? this.totalPriceElement.textContent : '— ₽'
            },
            // Также передаем сырые данные для обработки
            type: this.type,
            width: this.width,
            height: this.height,
            thickness: this.thickness,
            material: this.material,
            quantity: this.quantity,
            unit_price: this.unitPriceElement ? parseFloat(this.unitPriceElement.textContent.replace(' ₽/шт', '').replace(',', '.')) : 0,
            total_price: this.totalPriceElement ? parseFloat(this.totalPriceElement.textContent.replace(' ₽', '').replace(',', '.')) : 0
        };
        
        // Устанавливаем тип заявки и параметры
        data.type = 'calculator';
        data.parameters = parameters;
        
        const button = form.querySelector('button[type="submit"]');
        const originalText = button.innerHTML;
        
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Отправка...';
        button.disabled = true;
        
        fetch('/includes/api.php?action=save_lead', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showNotification(result.message || 'Запрос отправлен! Мы свяжемся с вами.', 'success');
                modal.remove();
            } else {
                showNotification(result.message || 'Ошибка при отправке', 'error');
                button.innerHTML = originalText;
                button.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Ошибка при подключении к серверу', 'error');
            button.innerHTML = originalText;
            button.disabled = false;
        });
    });
    
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
}


        
        printCalculation() {
            const printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Расчёт стоимости ZIP-пакетов</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        .print-header { text-align: center; margin-bottom: 30px; }
                        .print-header h1 { color: #2563eb; margin-bottom: 10px; }
                        .print-details { margin: 20px 0; }
                        .print-details p { margin: 5px 0; }
                        .print-results { background: #f5f7fa; padding: 20px; border-radius: 10px; margin: 20px 0; }
                        .result-item { display: flex; justify-content: space-between; margin: 10px 0; }
                        .total-price { font-size: 24px; font-weight: bold; color: #2563eb; }
                        .print-footer { margin-top: 30px; text-align: center; color: #666; font-size: 12px; }
                    </style>
                </head>
                <body>
                    <div class="print-header">
                        <h1>Расчёт стоимости ZIP-пакетов</h1>
                        <p>Расчёт по формуле на основе веса пленки</p>
                        <p>Дата: ${new Date().toLocaleDateString('ru-RU')}</p>
                    </div>
                    
                    <div class="print-details">
                        <p><strong>Тип пакета:</strong> ${this.typeSelect ? this.typeSelect.options[this.typeSelect.selectedIndex].text : this.type}</p>
                        <p><strong>Размер:</strong> ${this.width} × ${this.height} см</p>
                        <p><strong>Толщина:</strong> ${this.thickness} мкм</p>
                        <p><strong>Материал:</strong> ${this.materialSelect ? this.materialSelect.options[this.materialSelect.selectedIndex].text : this.material}</p>
                        <p><strong>Тираж:</strong> ${new Intl.NumberFormat('ru-RU').format(this.quantity)} шт</p>
                    </div>
                    
                    <div class="print-results">
                        <div class="result-item">
                            <span>Цена за штуку:</span>
                            <span>${this.unitPriceElement ? this.unitPriceElement.textContent : '— ₽'}</span>
                        </div>
                        <div class="result-item">
                            <span>Общая стоимость:</span>
                            <span class="total-price">${this.totalPriceElement ? this.totalPriceElement.textContent : '— ₽'}</span>
                        </div>
                        <div class="result-item">
                            <span>Условия цены:</span>
                            <span>${this.priceTierElement ? this.priceTierElement.textContent : '—'}</span>
                        </div>
                    </div>
                    
                    <div class="print-footer">
                        <p>Расчёт выполнен на сайте ZLOCK</p>
                        <p>www.zip.na4u.ru | 8 (800) 123-45-67</p>
                    </div>
                </body>
                </html>
            `;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.print();
        }
        
        showNotification(message, type = 'info') {
            if (window.showNotification) {
                window.showNotification(message, type);
            } else {
                alert(message);
            }
        }
    }


    // Инициализация при загрузке страницы
    document.addEventListener('DOMContentLoaded', function() {
        // Создаем глобальный объект калькулятора
        window.zipCalculator = new ZipCalculatorJS();
        window.zipCalculator.init();
        
        console.log('Калькулятор инициализирован');
    });
    </script>
    
    <style>
    .cost-breakdown {
        background: #f8fafc;
        border-radius: 8px;
        padding: 1rem;
        margin-top: 1rem;
        border-left: 4px solid #3b82f6;
    }
    
    .breakdown-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
        color: #1e40af;
        font-weight: 600;
    }
    
    .breakdown-item {
        display: flex;
        justify-content: space-between;
        padding: 0.25rem 0;
        font-size: 0.9rem;
        color: #4b5563;
    }
    
    .breakdown-item:not(:last-child) {
        border-bottom: 1px solid #e5e7eb;
    }
    
    .weight-info {
        background: #f0f9ff;
        padding: 0.75rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        border-left: 3px solid #0ea5e9;
        font-weight: 500;
    }
    
    .result-note {
        margin-top: 1rem;
        padding: 0.75rem;
        background: #fefce8;
        border-radius: 8px;
        border-left: 3px solid #f59e0b;
        font-size: 0.85rem;
        color: #92400e;
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .result-note i {
        margin-top: 2px;
    }
    </style>
    <?php
    return ob_get_clean();
}