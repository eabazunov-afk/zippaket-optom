<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Заполнение счета</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
        .search-section { background: #e3f2fd; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .type-selector { margin: 15px 0; }
        .type-selector label { margin-right: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        input, textarea, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .row { display: flex; gap: 20px; }
        .col { flex: 1; }
        .bank-details { background: #fff3e0; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .invoice-items { margin: 20px 0; }
        .item-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; }
        .item-row input { flex: 1; }
        .btn { background: #4CAF50; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .btn:hover { background: #45a049; }
        .btn-small { background: #f44336; padding: 8px 12px; font-size: 14px; }
        .result { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-top: 20px; }
        .loading { display: none; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧾 Заполнение счета на оплату</h1>
        
        <div class="search-section">
            <h3>Поиск контрагента</h3>
            <div class="type-selector">
                <label><input type="radio" name="type" value="auto" checked> Авто (любой)</label>
                <label><input type="radio" name="type" value="ip"> Только ИП</label>
                <label><input type="radio" name="type" value="ooo"> Только ООО</label>
            </div>
            
            <div class="row">
                <div class="col">
                    <input type="text" id="inn" placeholder="Введите ИНН" value="7707083893">
                </div>
                <div class="col">
                    <button class="btn" onclick="searchCompany()">Загрузить данные</button>
                </div>
            </div>
            <div class="loading" id="loading">Поиск...</div>
        </div>

        <form id="invoiceForm">
            <div class="row">
                <div class="col">
                    <h3>Продавец (вы)</h3>
                    <div class="form-group">
                        <label>Организация</label>
                        <input type="text" id="seller_name" value="ООО Ромашка">
                    </div>
                    <div class="row">
                        <div class="col">
                            <label>ИНН</label>
                            <input type="text" id="seller_inn" value="1234567890">
                        </div>
                        <div class="col">
                            <label>КПП</label>
                            <input type="text" id="seller_kpp" value="123456789">
                        </div>
                    </div>
                </div>
                
                <div class="col">
                    <h3>Покупатель</h3>
                    <div id="buyer_data">
                        <div class="form-group">
                            <label>Организация / ФИО</label>
                            <input type="text" id="buyer_name">
                        </div>
                        <div class="row">
                            <div class="col">
                                <label>ИНН</label>
                                <input type="text" id="buyer_inn">
                            </div>
                            <div class="col">
                                <label>КПП</label>
                                <input type="text" id="buyer_kpp">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Юридический адрес</label>
                            <input type="text" id="buyer_address">
                        </div>
                        <div class="form-group">
                            <label>Руководитель</label>
                            <input type="text" id="buyer_director">
                        </div>
                    </div>
                </div>
            </div>

            <div class="bank-details">
                <h3>Банковские реквизиты покупателя</h3>
                <div class="row">
                    <div class="col">
                        <label>Банк</label>
                        <input type="text" id="buyer_bank_name">
                    </div>
                    <div class="col">
                        <label>БИК</label>
                        <input type="text" id="buyer_bank_bik">
                    </div>
                </div>
                <div class="row">
                    <div class="col">
                        <label>Расчетный счет</label>
                        <input type="text" id="buyer_bank_account">
                    </div>
                    <div class="col">
                        <label>Корр. счет</label>
                        <input type="text" id="buyer_bank_corr">
                    </div>
                </div>
            </div>

            <h3>Товары/услуги</h3>
            <div class="invoice-items" id="items">
                <div class="item-row">
                    <input type="text" placeholder="Наименование" class="item-name">
                    <input type="number" placeholder="Кол-во" class="item-qty" value="1" style="width: 80px;">
                    <input type="text" placeholder="Ед." class="item-unit" value="шт" style="width: 60px;">
                    <input type="number" placeholder="Цена" class="item-price" value="1000">
                    <input type="number" placeholder="Сумма" class="item-total" readonly>
                    <button type="button" class="btn-small" onclick="removeItem(this)">×</button>
                </div>
            </div>
            <button type="button" class="btn" onclick="addItem()">+ Добавить позицию</button>

            <div style="margin: 30px 0; text-align: right;">
                <h3>Итого: <span id="totalAmount">0</span> руб.</h3>
            </div>

            <div class="form-group">
                <label>Номер счета</label>
                <input type="text" id="invoice_number" value="1">
            </div>
            <div class="form-group">
                <label>Дата счета</label>
                <input type="date" id="invoice_date" value="<?php echo date('Y-m-d'); ?>">
            </div>

            <button type="button" class="btn" onclick="generateInvoice()" style="width: 100%;">Сформировать счет</button>
        </form>

        <div class="result" id="result"></div>
    </div>

    <script>
    async function searchCompany() {
        const inn = document.getElementById('inn').value;
        const type = document.querySelector('input[name="type"]:checked').value;
        
        document.getElementById('loading').style.display = 'block';
        
        try {
            const response = await fetch(`get_company_data.php?inn=${inn}&type=${type}`);
            const data = await response.json();
            
            if (data.error) {
                alert('Ошибка: ' + data.error);
                return;
            }
            
            // Заполняем данные покупателя
            document.getElementById('buyer_name').value = data.name_full || data.name_short;
            document.getElementById('buyer_inn').value = data.inn;
            document.getElementById('buyer_kpp').value = data.kpp;
            document.getElementById('buyer_address').value = data.address;
            document.getElementById('buyer_director').value = data.director;
            
            // Заполняем банковские реквизиты (если есть)
            if (data.bank) {
                document.getElementById('buyer_bank_name').value = data.bank.name || '';
                document.getElementById('buyer_bank_bik').value = data.bank.bik || '';
                document.getElementById('buyer_bank_account').value = data.bank.account || '';
                document.getElementById('buyer_bank_corr').value = data.bank.corr_account || '';
            }
            
            // Показываем дополнительную информацию
            let info = `Найдено: ${data.name_full}\n`;
            info += `Тип: ${data.type}\n`;
            info += `Статус: ${data.status}\n`;
            info += `ОГРН: ${data.ogrn}`;
            console.log(info);
            
        } catch (e) {
            alert('Ошибка загрузки: ' + e.message);
        } finally {
            document.getElementById('loading').style.display = 'none';
        }
    }

    function addItem() {
        const items = document.getElementById('items');
        const template = document.createElement('div');
        template.className = 'item-row';
        template.innerHTML = `
            <input type="text" placeholder="Наименование" class="item-name">
            <input type="number" placeholder="Кол-во" class="item-qty" value="1" style="width: 80px;">
            <input type="text" placeholder="Ед." class="item-unit" value="шт" style="width: 60px;">
            <input type="number" placeholder="Цена" class="item-price" value="1000">
            <input type="number" placeholder="Сумма" class="item-total" readonly>
            <button type="button" class="btn-small" onclick="removeItem(this)">×</button>
        `;
        items.appendChild(template);
        calculateAll();
    }

    function removeItem(btn) {
        btn.parentElement.remove();
        calculateAll();
    }

    function calculateAll() {
        let total = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
            const price = parseFloat(row.querySelector('.item-price').value) || 0;
            const sum = qty * price;
            row.querySelector('.item-total').value = sum.toFixed(2);
            total += sum;
        });
        document.getElementById('totalAmount').textContent = total.toFixed(2);
    }

    // Расчет при изменении
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('item-qty') || e.target.classList.contains('item-price')) {
            calculateAll();
        }
    });

    function generateInvoice() {
        const items = [];
        document.querySelectorAll('.item-row').forEach(row => {
            items.push({
                name: row.querySelector('.item-name').value,
                qty: row.querySelector('.item-qty').value,
                unit: row.querySelector('.item-unit').value,
                price: row.querySelector('.item-price').value,
                total: row.querySelector('.item-total').value
            });
        });

        const invoice = {
            seller: {
                name: document.getElementById('seller_name').value,
                inn: document.getElementById('seller_inn').value,
                kpp: document.getElementById('seller_kpp').value
            },
            buyer: {
                name: document.getElementById('buyer_name').value,
                inn: document.getElementById('buyer_inn').value,
                kpp: document.getElementById('buyer_kpp').value,
                address: document.getElementById('buyer_address').value,
                director: document.getElementById('buyer_director').value,
                bank: {
                    name: document.getElementById('buyer_bank_name').value,
                    bik: document.getElementById('buyer_bank_bik').value,
                    account: document.getElementById('buyer_bank_account').value,
                    corr: document.getElementById('buyer_bank_corr').value
                }
            },
            invoice: {
                number: document.getElementById('invoice_number').value,
                date: document.getElementById('invoice_date').value,
                items: items,
                total: document.getElementById('totalAmount').textContent
            }
        };

        document.getElementById('result').innerHTML = `
            <h3>Счет сформирован</h3>
            <pre>${JSON.stringify(invoice, null, 2)}</pre>
            <button class="btn" onclick="window.print()">Печать</button>
        `;
    }

    // Инициализация расчета
    setTimeout(calculateAll, 100);
    </script>
</body>
</html>