<?php

require_once 'config.php';

class Calculator {
    private $db;
    
    // Цены из прайс-листа для Пакет слайдер с бегунком
    private $sliderPrices = [
        '25*30' => [
            '60' => [
                'EVA' => ['opt300k' => 3.5, 'opt20k' => 3.99, 'retail' => 4.5],
                'ПВД' => ['opt300k' => 3.5, 'opt20k' => 3.99, 'retail' => 4.5]
            ]
        ],
        '30*35' => [
            '60' => [
                'EVA' => ['opt300k' => 4.9, 'opt20k' => 5.8, 'retail' => 6.5],
                'ПВД' => ['opt300k' => 4.9, 'opt20k' => 5.8, 'retail' => 6.5]
            ]
        ],
        '35*45' => [
            '60' => [
                'EVA' => ['opt300k' => 6.9, 'opt20k' => 7.5, 'retail' => 8.5],
                'ПВД' => ['opt300k' => 6.9, 'opt20k' => 7.5, 'retail' => 8.5]
            ]
        ],
        '40*50' => [
            '60' => [
                'ПВД' => ['opt300k' => 8.95, 'opt20k' => 9.95, 'retail' => 12]
            ],
            '90' => [
                'EVA' => ['opt300k' => 13.9, 'opt20k' => 16, 'retail' => 20],
                'ПВД' => ['opt300k' => 12.9, 'opt20k' => 15, 'retail' => 18]
            ]
        ],
        '50*60' => [
            '60' => [
                'EVA' => ['opt300k' => 13.9, 'opt20k' => 16, 'retail' => 20]
            ]
        ]
    ];
    
    // Цены из прайс-листа для Пакет с замком zip-lock
    private $ziplockPrices = [
        '4*6' => [
            '50' => [
                'default' => ['opt300k' => 0.26, 'opt20k' => 0.29, 'retail' => 0.35]
            ]
        ],
        '6*6' => [
            '40' => [
                'default' => ['opt300k' => 0.22, 'opt20k' => 0.26, 'retail' => 0.3]
            ]
        ],
        '6*8' => [
            '40' => [
                'default' => ['opt300k' => 0.25, 'opt20k' => 0.28, 'retail' => 0.3]
            ]
        ],
        '10*15' => [
            '35' => [
                'default' => ['opt300k' => 0.65, 'opt20k' => 0.8, 'retail' => 1]
            ],
            '50' => [
                'default' => ['opt300k' => 0.85, 'opt20k' => 0.95, 'retail' => 1.2]
            ]
        ],
        '25*30' => [
            '60' => [
                'default' => ['opt300k' => 2.59, 'opt20k' => 2.99, 'retail' => 3.9],
                'with_hole' => ['opt300k' => 2.59, 'opt20k' => 2.99, 'retail' => 3.9]
            ]
        ],
        '35*45' => [
            '60' => [
                'default' => ['opt300k' => 5.9, 'opt20k' => 6.9, 'retail' => 8],
                'with_hole' => ['opt300k' => 5.9, 'opt20k' => 6.9, 'retail' => 8]
            ]
        ]
    ];
    
    public function __construct() {
        $this->db = getDbConnection();
    }
    
    /**
     * Рассчитать стоимость на основе прайс-листа
     */
    public function calculatePrice($type, $width, $height, $thickness, $material = null, $quantity = 1000) {
        // Формируем ключ размера
        $sizeKey = $width . '*' . $height;
        
        // Определяем тип пакета
        if ($type === 'slider') {
            $prices = $this->sliderPrices;
            $factorType = 'slider';
        } else {
            $prices = $this->ziplockPrices;
            $factorType = 'ziplock';
        }
        
        // Проверяем точное совпадение
        if (isset($prices[$sizeKey][$thickness])) {
            $thicknessPrices = $prices[$sizeKey][$thickness];
            
            // Для слайдера проверяем материал
            if ($type === 'slider') {
                if (isset($thicknessPrices[$material])) {
                    $priceInfo = $thicknessPrices[$material];
                } else {
                    // Если материала нет, берем первый доступный
                    $priceInfo = reset($thicknessPrices);
                }
            } else {
                // Для зиплока
                if (isset($thicknessPrices['default'])) {
                    $priceInfo = $thicknessPrices['default'];
                } else {
                    $priceInfo = reset($thicknessPrices);
                }
            }
        } else {
            // Аппроксимация для промежуточных размеров
            return $this->calculateApproximatePrice($type, $width, $height, $thickness, $material, $factorType);
        }
        
        // Определяем цену в зависимости от количества
        if ($quantity >= 300000) {
            $unitPrice = $priceInfo['opt300k'];
        } elseif ($quantity >= 20000) {
            $unitPrice = $priceInfo['opt20k'];
        } elseif ($quantity >= 3000) {
            $unitPrice = $priceInfo['retail'];
        } else {
            // Меньше 3000 - розничная цена + 20%
            $unitPrice = $priceInfo['retail'] * 1.2;
        }
        
        $totalPrice = $unitPrice * $quantity;
        
        return [
            'success' => true,
            'unit_price' => round($unitPrice, 2),
            'total_price' => round($totalPrice, 2),
            'currency' => '₽',
            'quantity' => $quantity,
            'quantity_tier' => $this->getQuantityTier($quantity)
        ];
    }
    
    /**
     * Аппроксимация цены для промежуточных размеров
     */
    private function calculateApproximatePrice($type, $width, $height, $thickness, $material, $factorType) {
        // Находим ближайшие размеры из прайса
        $closestSize = $this->findClosestSize($type, $width, $height);
        
        if (!$closestSize) {
            // Если нет подходящего размера, рассчитываем по площади
            return $this->calculateByArea($type, $width, $height, $thickness, $material);
        }
        
        list($closestWidth, $closestHeight) = explode('*', $closestSize);
        
        // Получаем цену ближайшего размера
        if ($type === 'slider') {
            $basePrice = $this->sliderPrices[$closestSize][$thickness][$material]['opt20k'] ?? 0;
        } else {
            $basePrice = $this->ziplockPrices[$closestSize][$thickness]['default']['opt20k'] ?? 0;
        }
        
        if ($basePrice === 0) {
            return $this->calculateByArea($type, $width, $height, $thickness, $material);
        }
        
        // Корректируем цену по площади
        $closestArea = $closestWidth * $closestHeight;
        $currentArea = $width * $height;
        
        $areaRatio = $currentArea / $closestArea;
        $unitPrice = $basePrice * $areaRatio * 0.9; // Коэффициент 0.9 для аппроксимации
        
        // Минимальная цена
        $unitPrice = max($unitPrice, 0.2);
        
        return [
            'success' => true,
            'unit_price' => round($unitPrice, 2),
            'total_price' => round($unitPrice * 10000, 2),
            'currency' => '₽',
            'quantity' => 10000,
            'quantity_tier' => 'opt20k',
            'is_approximate' => true
        ];
    }
    
    /**
     * Расчёт по площади (запасной вариант)
     */
    private function calculateByArea($type, $width, $height, $thickness, $material) {
        $area = $width * $height; // в см²
        
        // Базовые цены за см²
        $basePrices = [
            'slider' => 0.0015, // руб/см² для слайдера
            'ziplock' => 0.0012 // руб/см² для зиплока
        ];
        
        // Коэффициент толщины
        $thicknessFactors = [
            '35' => 0.8,
            '40' => 0.9,
            '50' => 1.0,
            '60' => 1.1,
            '90' => 1.3
        ];
        
        // Коэффициент материала
        $materialFactors = [
            'EVA' => 1.1,
            'ПВД' => 1.0
        ];
        
        $basePrice = $basePrices[$type] ?? $basePrices['ziplock'];
        $thicknessFactor = $thicknessFactors[$thickness] ?? 1.0;
        $materialFactor = $materialFactors[$material] ?? 1.0;
        
        $unitPrice = $area * $basePrice * $thicknessFactor * $materialFactor;
        $unitPrice = max($unitPrice, 0.2); // Минимальная цена
        
        return [
            'success' => true,
            'unit_price' => round($unitPrice, 2),
            'total_price' => round($unitPrice * 10000, 2),
            'currency' => '₽',
            'quantity' => 10000,
            'quantity_tier' => 'opt20k',
            'is_approximate' => true
        ];
    }
    
    /**
     * Найти ближайший размер в прайсе
     */
    private function findClosestSize($type, $width, $height) {
        $prices = ($type === 'slider') ? $this->sliderPrices : $this->ziplockPrices;
        $sizes = array_keys($prices);
        
        $closestSize = null;
        $minDifference = PHP_INT_MAX;
        
        foreach ($sizes as $size) {
            list($sizeWidth, $sizeHeight) = explode('*', $size);
            
            $difference = abs($width - $sizeWidth) + abs($height - $sizeHeight);
            
            if ($difference < $minDifference) {
                $minDifference = $difference;
                $closestSize = $size;
            }
        }
        
        return $closestSize;
    }
    
    /**
     * Определить уровень цены по количеству
     */
    private function getQuantityTier($quantity) {
        if ($quantity >= 300000) {
            return 'opt300k';
        } elseif ($quantity >= 20000) {
            return 'opt20k';
        } elseif ($quantity >= 3000) {
            return 'retail';
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
                'ziplock' => 'Пакет с замком zip-lock'
            ],
            'widths' => range(5, 50, 1),
            'heights' => range(15, 60, 1),
            'thickness' => [35, 40, 50, 60, 90],
            'materials' => [
                'EVA' => 'EVA (матовый)',
                'ПВД' => 'ПВД (прозрачный)'
            ],
            'quantities' => [
                1000 => '1,000 шт',
                3000 => '3,000 шт',
                10000 => '10,000 шт',
                20000 => '20,000 шт',
                50000 => '50,000 шт',
                100000 => '100,000 шт',
                200000 => '200,000 шт',
                300000 => '300,000 шт',
                500000 => '500,000 шт',
                1000000 => '1,000,000 шт'
            ]
        ];
    }
}

/**
 * Функция для отображения формы калькулятора
 */
function displayCalculatorForm() {
    $calculator = new Calculator();
    $options = $calculator->getAvailableOptions();
    
    ob_start();
    ?>
    <div class="calculator" id="zipCalculator">
        <div class="calculator-header">
            <h2 class="section-title">Калькулятор стоимости</h2>
            <p class="section-subtitle">Рассчитайте стоимость заказа на основе прайс-листа</p>
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
                        <label for="material" id="materialLabel" style="display: none;">
                            <i class="fas fa-layer-group"></i>
                            Материал
                        </label>
                        <select id="material" name="material" class="form-control" style="display: none;">
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
                                   step="5" 
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
                                   step="5" 
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
                                <option value="<?php echo $value; ?>" <?php echo $value == 20000 ? 'selected' : ''; ?>>
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
                    <div class="result-item">
                        <div class="result-label">Цена за штуку:</div>
                        <div class="result-value" id="unitPrice">— ₽</div>
                    </div>
                    
                    <div class="result-item">
                        <div class="result-label">Общая стоимость:</div>
                        <div class="result-value" id="totalPrice">— ₽</div>
                    </div>
                    
                    <div class="result-item">
                        <div class="result-label">Тираж:</div>
                        <div class="result-value" id="quantityDisplay">— шт</div>
                    </div>
                    
                    <div class="result-item">
                        <div class="result-label">Условия цены:</div>
                        <div class="result-value" id="priceTier">—</div>
                    </div>
                    
                    <div class="result-note" id="resultNote"></div>
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
            this.quantity = 20000;
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
            
            this.unitPriceElement = document.getElementById('unitPrice');
            this.totalPriceElement = document.getElementById('totalPrice');
            this.quantityDisplayElement = document.getElementById('quantityDisplay');
            this.priceTierElement = document.getElementById('priceTier');
            this.resultNoteElement = document.getElementById('resultNote');
            this.sizeInfoElement = document.getElementById('sizeInfo');
            
            this.saveBtn = document.getElementById('saveCalculation');
            this.requestBtn = document.getElementById('requestOffer');
            this.printBtn = document.getElementById('printCalculation');
            
            if (!this.typeSelect) {
                console.error('Элемент калькулятора не найден');
                return;
            }
            
            this.bindEvents();
            this.updateMaterialVisibility();
            this.updateSizeInfo();
            this.calculate();
            
            this.isInitialized = true;
            console.log('Калькулятор инициализирован');
        }
        
        bindEvents() {
            this.typeSelect.addEventListener('change', () => {
                this.type = this.typeSelect.value;
                this.updateMaterialVisibility();
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
            
            if (this.quantitySelect) {
                this.quantitySelect.addEventListener('change', () => {
                    this.quantity = parseInt(this.quantitySelect.value) || 20000;
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
        }
        
        updateMaterialVisibility() {
            if (!this.materialSelect || !this.materialLabel) return;
            
            if (this.type === 'slider') {
                this.materialSelect.style.display = 'block';
                this.materialLabel.style.display = 'block';
                this.material = this.materialSelect.value;
            } else {
                this.materialSelect.style.display = 'none';
                this.materialLabel.style.display = 'none';
                this.material = null;
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
            // Форматируем цены
            const unitPriceFormatted = new Intl.NumberFormat('ru-RU', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(data.unit_price || 0);
            
            const totalPriceFormatted = new Intl.NumberFormat('ru-RU', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(data.total_price || 0);
            
            const quantityFormatted = new Intl.NumberFormat('ru-RU').format(this.quantity);
            
            // Обновляем UI
            if (this.unitPriceElement) {
                this.unitPriceElement.textContent = unitPriceFormatted + ' ₽';
            }
            
            if (this.totalPriceElement) {
                this.totalPriceElement.textContent = totalPriceFormatted + ' ₽';
            }
            
            if (this.quantityDisplayElement) {
                this.quantityDisplayElement.textContent = quantityFormatted + ' шт';
            }
            
            // Определяем уровень цены
            let priceTierText = '';
            const tier = data.quantity_tier || 'opt20k';
            
            switch (tier) {
                case 'opt300k':
                    priceTierText = 'Опт от 300,000 шт';
                    break;
                case 'opt20k':
                    priceTierText = 'Опт от 20,000 шт';
                    break;
                case 'retail':
                    priceTierText = 'Розница от 3,000 шт';
                    break;
                default:
                    priceTierText = 'Мелкий опт';
            }
            
            if (this.priceTierElement) {
                this.priceTierElement.textContent = priceTierText;
            }
            
            // Показываем заметку если цена аппроксимирована
            if (this.resultNoteElement) {
                if (data.is_approximate) {
                    this.resultNoteElement.innerHTML = `
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Цена рассчитана приблизительно. Для точного расчёта свяжитесь с менеджером.</span>
                    `;
                    this.resultNoteElement.style.display = 'flex';
                } else {
                    this.resultNoteElement.style.display = 'none';
                }
            }
        }
        
        showLoading() {
            if (this.unitPriceElement) this.unitPriceElement.textContent = '... ₽';
            if (this.totalPriceElement) this.totalPriceElement.textContent = '... ₽';
            if (this.quantityDisplayElement) this.quantityDisplayElement.textContent = '... шт';
            if (this.priceTierElement) this.priceTierElement.textContent = 'Расчёт...';
        }
        
        showError(message) {
            if (this.unitPriceElement) this.unitPriceElement.textContent = '— ₽';
            if (this.totalPriceElement) this.totalPriceElement.textContent = '— ₽';
            if (this.quantityDisplayElement) this.quantityDisplayElement.textContent = '— шт';
            if (this.priceTierElement) this.priceTierElement.textContent = 'Ошибка';
            
            if (this.resultNoteElement) {
                this.resultNoteElement.innerHTML = `
                    <i class="fas fa-exclamation-circle"></i>
                    <span>${message}</span>
                `;
                this.resultNoteElement.style.display = 'flex';
            }
        }
        
        // В calculator.php в методе saveCalculation() класса ZipCalculatorJS обновите:

saveCalculation() {
    const data = {
        type: this.type,
        width: this.width,
        height: this.height,
        thickness: this.thickness,
        material: this.material,
        quantity: this.quantity
    };
    
    // Отправляем на сервер для сохранения в БД
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
            this.showNotification(`Расчёт сохранен! ID: ${result.calculation_id}`, 'success');
        } else {
            this.showNotification(result.message || 'Ошибка при сохранении', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        this.showNotification('Ошибка при подключении к серверу', 'error');
    });
}
        


requestOffer() {
    if (!this.typeSelect || !this.materialSelect) return;
    
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
                        <p>Тип: ${this.typeSelect.options[this.typeSelect.selectedIndex].text}</p>
                        <p>Размер: ${this.width}×${this.height} см</p>
                        <p>Толщина: ${this.thickness} мкм</p>
                        ${this.type === 'slider' ? `<p>Материал: ${this.materialSelect.options[this.materialSelect.selectedIndex].text}</p>` : ''}
                        <p>Тираж: ${new Intl.NumberFormat('ru-RU').format(this.quantity)} шт</p>
                        <p>Примерная стоимость: ${this.totalPriceElement ? this.totalPriceElement.textContent : '— ₽'}</p>
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
        
        // Добавляем данные расчета
        data.calculation = {
            type: this.type,
            width: this.width,
            height: this.height,
            thickness: this.thickness,
            material: this.material,
            quantity: this.quantity,
            unit_price: this.unitPriceElement ? this.unitPriceElement.textContent.replace(' ₽', '') : 0,
            total_price: this.totalPriceElement ? this.totalPriceElement.textContent.replace(' ₽', '') : 0
        };
        
        const button = form.querySelector('button[type="submit"]');
        const originalText = button.innerHTML;
        
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Отправка...';
        button.disabled = true;
        
        // Отправляем на сервер
        fetch('/includes/api.php?action=request_offer', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                this.showNotification(result.message || 'Запрос отправлен! Мы свяжемся с вами.', 'success');
                modal.remove();
            } else {
                this.showNotification(result.message || 'Ошибка при отправке', 'error');
                button.innerHTML = originalText;
                button.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            this.showNotification('Ошибка при подключении к серверу', 'error');
            button.innerHTML = originalText;
            button.disabled = false;
        });
    });
    
    // Закрытие по клику вне модального окна
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
    
    // Закрытие по Escape
    document.addEventListener('keydown', function closeModalOnEscape(e) {
        if (e.key === 'Escape') {
            modal.remove();
            document.removeEventListener('keydown', closeModalOnEscape);
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
                        <p>Дата: ${new Date().toLocaleDateString('ru-RU')}</p>
                    </div>
                    
                    <div class="print-details">
                        <p><strong>Тип пакета:</strong> ${this.typeSelect ? this.typeSelect.options[this.typeSelect.selectedIndex].text : this.type}</p>
                        <p><strong>Размер:</strong> ${this.width} × ${this.height} см</p>
                        <p><strong>Толщина:</strong> ${this.thickness} мкм</p>
                        ${this.type === 'slider' ? `<p><strong>Материал:</strong> ${this.materialSelect ? this.materialSelect.options[this.materialSelect.selectedIndex].text : this.material}</p>` : ''}
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
                        <p>Расчёт выполнен на сайте ZIP-Завод</p>
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
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => notification.classList.add('show'), 100);
            
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
    }

    // Инициализация при загрузке страницы
    document.addEventListener('DOMContentLoaded', function() {
        // Создаем глобальный объект калькулятора
        window.zipCalculator = new ZipCalculatorJS();
        window.zipCalculator.init();
        
        // Инициализация корзины
        if (typeof window.offerCart === 'undefined') {
            window.offerCart = new OfferCart();
        }
        
        console.log('Калькулятор и корзина инициализированы');
    });
    </script>
    
    <style>
    .notification {
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        z-index: 1003;
        transform: translateX(120%);
        transition: transform 0.3s ease;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        max-width: 350px;
        backdrop-filter: blur(10px);
    }
    
    .notification.show {
        transform: translateX(0);
    }
    
    .notification-success {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        border-left: 4px solid #047857;
    }
    
    .notification-error {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        border-left: 4px solid #b91c1c;
    }
    
    .notification-info {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
        border-left: 4px solid #1e40af;
    }
    
    .add-to-cart.added {
        background: #10b981 !important;
        border-color: #10b981 !important;
        color: white !important;
    }
    </style>
    <?php
    return ob_get_clean();
}