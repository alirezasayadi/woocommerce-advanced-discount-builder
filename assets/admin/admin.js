jQuery(function ($) {
    var timer = null;

    // ====== افزودن کوپن جدید ======

    $('#add-coupon').on('click', function () {
        var couponCount = $('#wdb-coupons-container .coupon-box').length;

        var html = '<div class="coupon-box" data-coupon-index="' + couponCount + '">'
            + '<div class="coupon-box-header">'
            + '<span class="coupon-box-title">'
            + '🏷️ کد تخفیف:'
            + '<input type="text" name="coupon_rules[' + couponCount + '][code]" class="coupon-code-input" placeholder="مثال: SUMMER2026">'
            + '</span>'
            + '<span class="coupon-actions">'
            + '<button type="button" class="button add-rule-to-coupon">➕ افزودن قانون</button>'
            + '<button type="button" class="button remove-coupon" style="color:#a00;">🗑️ حذف کد</button>'
            + '</span>'
            + '</div>'

            + '<div class="other-products-section" style="background:#f5f9ff;padding:15px;border-radius:4px;margin-bottom:15px;border:1px solid #d1e0f0;">'
            + '<h4 style="margin:0 0 10px 0;color:#0066a0;">🔄 تخفیف روی سایر محصولات (غیرمشمول)</h4>'
            + '<div style="margin-top:5px;">'
            + '<label style="margin-right:15px;"><input type="radio" name="other_products[' + couponCount + '][type]" value="none" checked> بدون تخفیف</label>'
            + '<label style="margin-right:15px;"><input type="radio" name="other_products[' + couponCount + '][type]" value="percent"> درصدی</label>'
            + '<label style="margin-right:15px;"><input type="radio" name="other_products[' + couponCount + '][type]" value="fixed"> مبلغ ثابت</label>'
            + '<input type="number" step="0.01" min="0" name="other_products[' + couponCount + '][value]" class="other-products-value" placeholder="مقدار تخفیف سایر محصولات" style="width:150px;max-width:100%;margin-top:5px;">'
            + '<span style="font-size:12px;color:#666;display:block;margin-top:3px;">این تخفیف روی محصولاتی که در قوانین بالا مشخص نشده‌اند اعمال می‌شود.</span>'
            + '</div></div>'

            + '<div class="free-shipping-section" style="background:#f0fff4;padding:15px;border-radius:4px;margin-bottom:15px;border:1px solid #c6e6c6;">'
            + '<label style="display:flex;align-items:center;gap:8px;cursor:pointer;">'
            + '<input type="checkbox" name="free_shipping[' + couponCount + ']" value="1" class="wdb-free-shipping-checkbox">'
            + '<span style="font-weight:bold;color:#2e7d32;">🚚 ارسال رایگان برای این کد تخفیف</span>'
            + '</label>'
            + '<p style="margin:5px 0 0 0;font-size:12px;color:#555;">با فعال کردن این گزینه، هنگام استفاده از این کد تخفیف، فقط گزینه ارسال رایگان نمایش داده می‌شود.</p>'
            + '</div>'

            + '<div class="coupon-rules-container">'
            + '<div class="empty-message">هنوز قانونی تعریف نشده است. دکمه "افزودن قانون" را بزنید.</div>'
            + '</div>'
            + '</div>';

        $('#wdb-coupons-container').append(html);
    });

    // ====== حذف کوپن ======

    $(document).on('click', '.remove-coupon', function () {
        if (confirm('آیا از حذف این کد تخفیف مطمئن هستید؟')) {
            $(this).closest('.coupon-box').remove();
        }
    });

    // ====== افزودن قانون ======

    $(document).on('click', '.add-rule-to-coupon', function () {
        var couponBox = $(this).closest('.coupon-box');
        var couponIndex = couponBox.data('coupon-index');
        var rulesContainer = couponBox.find('.coupon-rules-container');
        var ruleIndex = rulesContainer.find('.rule-box').length;

        rulesContainer.find('.empty-message').remove();

        var html = '<div class="rule-box" data-rule-index="' + ruleIndex + '">'
            + '<div class="rule-header"><div>'
            + '<select name="coupon_rules[' + couponIndex + '][rules][' + ruleIndex + '][type]" class="rule-type-select">'
            + '<option value="percent">درصد (%)</option>'
            + '<option value="fixed">مبلغ ثابت (تومان)</option>'
            + '</select>'
            + '<input type="number" step="0.01" min="0" name="coupon_rules[' + couponIndex + '][rules][' + ruleIndex + '][value]" class="rule-value-input" placeholder="مقدار تخفیف">'
            + '</div><div>'
            + '<button type="button" class="button remove-rule">🗑️ حذف قانون</button>'
            + '</div></div>'

            + '<div class="selection-type">'
            + '<label><input type="radio" name="selection_type_' + couponIndex + '_' + ruleIndex + '" value="products" class="selection-type-radio" data-coupon="' + couponIndex + '" data-rule="' + ruleIndex + '" checked> انتخاب بر اساس محصول</label>'
            + '<label><input type="radio" name="selection_type_' + couponIndex + '_' + ruleIndex + '" value="categories" class="selection-type-radio" data-coupon="' + couponIndex + '" data-rule="' + ruleIndex + '"> انتخاب بر اساس دسته‌بندی</label>'
            + '<label><input type="radio" name="selection_type_' + couponIndex + '_' + ruleIndex + '" value="brands" class="selection-type-radio" data-coupon="' + couponIndex + '" data-rule="' + ruleIndex + '"> انتخاب بر اساس برند</label>'
            + '</div>'

            + '<div class="search-section products-search-section">'
            + '<input type="text" class="search-input product-search" placeholder="🔍 جستجوی محصول..." data-coupon-index="' + couponIndex + '" data-rule-index="' + ruleIndex + '" data-search-type="products">'
            + '<div class="results" style="display:none;"></div>'
            + '<div class="selected-items selected-products"><span class="empty-message" style="font-size:13px;">هیچ محصولی انتخاب نشده است.</span></div>'
            + '</div>'

            + '<div class="search-section categories-search-section" style="display:none;">'
            + '<input type="text" class="search-input category-search" placeholder="🔍 جستجوی دسته‌بندی..." data-coupon-index="' + couponIndex + '" data-rule-index="' + ruleIndex + '" data-search-type="categories">'
            + '<div class="results" style="display:none;"></div>'
            + '<div class="selected-items selected-categories"><span class="empty-message" style="font-size:13px;">هیچ دسته‌بندی انتخاب نشده است.</span></div>'
            + '</div>'

            + '<div class="search-section brands-search-section" style="display:none;">'
            + '<input type="text" class="search-input brand-search" placeholder="🔍 جستجوی برند..." data-coupon-index="' + couponIndex + '" data-rule-index="' + ruleIndex + '" data-search-type="brands">'
            + '<div class="results" style="display:none;"></div>'
            + '<div class="selected-items selected-brands"><span class="empty-message" style="font-size:13px;">هیچ برندی انتخاب نشده است.</span></div>'
            + '</div>'

            + '</div>';

        rulesContainer.append(html);
    });

    // ====== تغییر نوع انتخاب ======

    $(document).on('change', '.selection-type-radio', function () {
        var ruleBox = $(this).closest('.rule-box');
        var value = $(this).val();

        ruleBox.find('.search-section').hide();

        if (value === 'products') {
            ruleBox.find('.products-search-section').show();
        } else if (value === 'categories') {
            ruleBox.find('.categories-search-section').show();
        } else if (value === 'brands') {
            ruleBox.find('.brands-search-section').show();
        }
    });

    // ====== جستجو ======

    function performSearch(input, action) {
        clearTimeout(timer);
        var box = $(input);
        var ruleBox = box.closest('.rule-box');
        var resultsContainer = ruleBox.find('.results');

        timer = setTimeout(function () {
            var q = box.val().trim();

            if (q.length < 2) {
                resultsContainer.hide().empty();
                return;
            }

            $.get(wdbp_ajax.ajax_url, { action: action, q: q }, function (res) {
                var html = '';
                if (!res.length) {
                    html = '<div class="item" style="padding:5px;color:#666;">❌ هیچ نتیجه‌ای یافت نشد.</div>';
                } else {
                    res.forEach(function (item) {
                        var icon = action === 'wdb_search_products' ? '📦' :
                                   action === 'wdb_search_categories' ? '📂' : '🏷️';
                        html += '<div class="item" data-id="' + item.id + '" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #eee;">'
                            + icon + ' ' + item.text + '</div>';
                    });
                }
                resultsContainer.html(html).show();
            });
        }, 300);
    }

    $(document).on('keyup', '.product-search', function () {
        performSearch(this, 'wdb_search_products');
    });

    $(document).on('keyup', '.category-search', function () {
        performSearch(this, 'wdb_search_categories');
    });

    $(document).on('keyup', '.brand-search', function () {
        performSearch(this, 'wdb_search_brands');
    });

    // ====== انتخاب آیتم از نتایج جستجو ======

    $(document).on('click', '.results .item', function () {
        var id = $(this).data('id');
        if (!id) return;

        var ruleBox = $(this).closest('.rule-box');
        var text = $(this).text().replace(/^[^\s]+\s/, '');
        var searchInput = ruleBox.find('.search-input:visible');
        var searchType = searchInput.data('search-type');

        var mapping = {
            products:   { containerClass: 'selected-products',   itemClass: 'selected-product',   fieldName: 'products',   icon: '📦' },
            categories: { containerClass: 'selected-categories', itemClass: 'selected-category', fieldName: 'categories', icon: '📂' },
            brands:     { containerClass: 'selected-brands',     itemClass: 'selected-brand',    fieldName: 'brands',     icon: '🏷️' }
        };

        var cfg = mapping[searchType];
        if (!cfg) return;

        var container = ruleBox.find('.' + cfg.containerClass);

        if (container.find('.' + cfg.itemClass + '[data-id="' + id + '"]').length) {
            ruleBox.find('.results').hide().empty();
            searchInput.val('');
            return;
        }

        var couponIndex = searchInput.data('coupon-index');
        var ruleIndex = searchInput.data('rule-index');

        container.find('.empty-message').remove();

        container.append(
            '<div class="selected-item ' + cfg.itemClass + '" data-id="' + id + '">'
            + cfg.icon + ' ' + text
            + '<input type="hidden" name="coupon_rules[' + couponIndex + '][rules][' + ruleIndex + '][' + cfg.fieldName + '][]" value="' + id + '">'
            + '<button type="button" class="remove-item">×</button>'
            + '</div>'
        );

        ruleBox.find('.results').hide().empty();
        searchInput.val('');
    });

    // ====== حذف آیتم انتخاب شده ======

    $(document).on('click', '.remove-item', function () {
        var parent = $(this).closest('.selected-items');
        $(this).closest('.selected-item').remove();

        if (parent.find('.selected-item').length === 0) {
            parent.html('<span class="empty-message" style="font-size:13px;">هیچ آیتمی انتخاب نشده است.</span>');
        }
    });

    // ====== حذف قانون ======

    $(document).on('click', '.remove-rule', function () {
        $(this).closest('.rule-box').remove();
    });

    // ====== بستن نتایج با کلیک بیرون ======

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.search-section').length) {
            $('.results').hide();
        }
    });

    // ====== فرمت اعداد ======

    function formatNumber(number) {
        return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // ====== ابزار اصلاح سفارشات ======

    $(document).on('click', '#wdb-fix-order-btn', function () {
        var orderId = $('#wdb-order-id-input').val();

        if (!orderId) {
            alert('لطفاً شماره سفارش را وارد کنید');
            return;
        }

        var resultDiv = $('#wdb-fix-order-result');
        resultDiv.show().html('<div style="padding:20px;text-align:center;">🔄 در حال پردازش...</div>').removeClass('success error warning');

        $.post(wdbp_ajax.ajax_url, {
            action: 'wdb_fix_single_order',
            order_id: orderId
        }, function (response) {
            if (response.success) {
                displayFixResults(response.data, resultDiv);
            } else {
                resultDiv.html('❌ خطا: ' + (response.data.message || 'خطای ناشناخته')).addClass('error').show();
            }
        });
    });

    $(document).on('click', '#wdb-remove-fee-btn', function () {
        var orderId = $('#wdb-remove-fee-order-id').val();

        if (!orderId) {
            alert('لطفاً شماره سفارش را وارد کنید');
            return;
        }

        if (!confirm('آیا از حذف تمام نرخ‌های سفارش #' + orderId + ' مطمئن هستید؟')) {
            return;
        }

        var resultDiv = $('#wdb-remove-fee-result');
        resultDiv.show().html('<div style="padding:20px;text-align:center;">🔄 در حال حذف...</div>').removeClass('success error warning');

        $.post(wdbp_ajax.ajax_url, {
            action: 'wdb_remove_fees',
            order_id: orderId
        }, function (response) {
            if (response.success) {
                var data = response.data;
                var html = '<h3 style="margin-top:0;">✅ ' + data.message + '</h3>';
                if (data.removed_names && data.removed_names.length > 0) {
                    html += '<p><strong>نرخ‌های حذف شده:</strong></p><ul>';
                    data.removed_names.forEach(function (name) {
                        html += '<li>' + name + '</li>';
                    });
                    html += '</ul>';
                }
                html += '<p><strong>مجموع جدید:</strong> ' + data.total + '</p>';
                resultDiv.html(html).addClass('success').show();
            } else {
                resultDiv.html('❌ خطا: ' + (response.data.message || 'خطای ناشناخته')).addClass('error').show();
            }
        });
    });

    // ====== اصلاح دسته‌ای ======

    var bulkFixInProgress = false;
    var bulkFixStopRequested = false;
    var totalOrdersWithCoupon = 0;

    $(document).on('click', '#wdb-fix-bulk-btn', function () {
        var couponCode = $('#wdb-coupon-code-input').val();

        if (!couponCode) {
            alert('لطفاً کد تخفیف را وارد کنید');
            return;
        }

        if (bulkFixInProgress) {
            bulkFixStopRequested = true;
            $(this).text('⏹️ در حال توقف...').prop('disabled', true);
            return;
        }

        if (!confirm('شروع اصلاح دسته‌ای سفارشات با کد ' + couponCode + '؟')) {
            return;
        }

        bulkFixInProgress = true;
        bulkFixStopRequested = false;

        var resultDiv = $('#wdb-fix-bulk-result');
        resultDiv.show().removeClass('success error warning').addClass('info');
        resultDiv.html('<h3>🔄 در حال دریافت تعداد سفارشات...</h3>');

        $.post(wdbp_ajax.ajax_url, {
            action: 'wdb_get_bulk_count',
            coupon_code: couponCode
        }, function (response) {
            if (response.success) {
                totalOrdersWithCoupon = response.data.total;

                resultDiv.html(
                    '<h3>🔄 در حال پردازش سفارشات...</h3>'
                    + '<p><strong>تعداد کل سفارشات:</strong> ' + formatNumber(totalOrdersWithCoupon) + '</p>'
                    + '<div style="margin:15px 0;">'
                    + '<div style="background:#e0e0e0;border-radius:10px;height:30px;overflow:hidden;position:relative;">'
                    + '<div id="wdb-progress-bar" style="background:linear-gradient(90deg,#4caf50,#8bc34a);height:100%;width:0%;transition:width 0.3s;border-radius:10px;"></div>'
                    + '<div id="wdb-progress-text" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-weight:bold;color:#333;">0%</div>'
                    + '</div></div>'
                    + '<div style="margin:10px 0;">'
                    + '<p><strong>پردازش شده:</strong> <span id="wdb-processed-count">0</span> از ' + formatNumber(totalOrdersWithCoupon) + '</p>'
                    + '<p><strong>سفارشات اصلاح شده:</strong> <span id="wdb-fixed-count">0</span></p>'
                    + '<p><strong>مجموع تخفیف:</strong> <span id="wdb-total-discount">0</span> تومان</p>'
                    + '</div>'
                    + '<button type="button" id="wdb-stop-bulk-btn" class="button" style="background:#f44336;color:#fff;border-color:#f44336;">⏹️ توقف</button>'
                );

                $('#wdb-fix-bulk-btn').text('⏹️ توقف عملیات');
                startBulkFix(couponCode, 0, 0, 0, 0);
            } else {
                resultDiv.html('❌ خطا: ' + (response.data.message || 'خطای ناشناخته')).addClass('error').show();
                bulkFixInProgress = false;
                $('#wdb-fix-bulk-btn').text('🔄 اصلاح دسته‌ای');
            }
        });
    });

    function startBulkFix(couponCode, offset, fixedOrders, fixedItems, totalDiscount) {
        if (bulkFixStopRequested) {
            finishBulkFix(couponCode, offset, fixedOrders, fixedItems, totalDiscount, true);
            return;
        }

        $.post(wdbp_ajax.ajax_url, {
            action: 'wdb_fix_bulk_orders_batch',
            coupon_code: couponCode,
            offset: offset,
            batch_size: 20
        }, function (response) {
            if (response.success) {
                var data = response.data;
                var newFixedOrders = fixedOrders + data.fixed_orders;
                var newFixedItems = fixedItems + data.fixed_items;
                var newTotalDiscount = totalDiscount + data.total_discount;
                var newOffset = data.next_offset;

                $('#wdb-processed-count').text(formatNumber(newOffset));
                $('#wdb-fixed-count').text(formatNumber(newFixedOrders));
                $('#wdb-total-discount').text(formatNumber(newTotalDiscount));

                var percent = 0;
                if (totalOrdersWithCoupon > 0) {
                    percent = Math.min(100, Math.round((newOffset / totalOrdersWithCoupon) * 100));
                }
                $('#wdb-progress-bar').css('width', percent + '%');
                $('#wdb-progress-text').text(percent + '%');

                if (data.has_more && !bulkFixStopRequested) {
                    setTimeout(function () {
                        startBulkFix(couponCode, newOffset, newFixedOrders, newFixedItems, newTotalDiscount);
                    }, 300);
                } else {
                    finishBulkFix(couponCode, newOffset, newFixedOrders, newFixedItems, newTotalDiscount, false);
                }
            } else {
                finishBulkFix(couponCode, offset, fixedOrders, fixedItems, totalDiscount, false, response.data.message);
            }
        });
    }

    function finishBulkFix(couponCode, offset, fixedOrders, fixedItems, totalDiscount, stopped, errorMessage) {
        bulkFixInProgress = false;
        $('#wdb-fix-bulk-btn').text('🔄 اصلاح دسته‌ای').prop('disabled', false);

        var resultDiv = $('#wdb-fix-bulk-result');

        if (stopped) {
            resultDiv.html(
                '<h3 style="color:#ff9800;">⏹️ عملیات متوقف شد</h3>'
                + '<p><strong>پردازش شده:</strong> ' + formatNumber(offset) + ' از ' + formatNumber(totalOrdersWithCoupon) + '</p>'
                + '<p><strong>سفارشات اصلاح شده:</strong> ' + formatNumber(fixedOrders) + '</p>'
                + '<p><strong>مجموع تخفیف:</strong> ' + formatNumber(totalDiscount) + ' تومان</p>'
            ).removeClass('info').addClass('warning').show();
        } else if (errorMessage) {
            resultDiv.html(
                '<h3 style="color:#f44336;">❌ خطا</h3>'
                + '<p>' + errorMessage + '</p>'
                + '<p><strong>پردازش شده:</strong> ' + formatNumber(offset) + ' از ' + formatNumber(totalOrdersWithCoupon) + '</p>'
            ).removeClass('info').addClass('error').show();
        } else {
            resultDiv.html(
                '<h3 style="color:#4caf50;">✅ عملیات با موفقیت انجام شد</h3>'
                + '<p><strong>کل سفارشات:</strong> ' + formatNumber(totalOrdersWithCoupon) + '</p>'
                + '<p><strong>سفارشات اصلاح شده:</strong> ' + formatNumber(fixedOrders) + '</p>'
                + '<p><strong>آیتم‌های اصلاح شده:</strong> ' + formatNumber(fixedItems) + '</p>'
                + '<p><strong>مجموع تخفیف:</strong> ' + formatNumber(totalDiscount) + ' تومان</p>'
            ).removeClass('info').addClass('success').show();
        }
    }

    $(document).on('click', '#wdb-stop-bulk-btn', function () {
        bulkFixStopRequested = true;
        $(this).text('⏹️ در حال توقف...').prop('disabled', true);
    });

    // ====== نمایش نتایج ======

    function displayFixResults(data, resultDiv) {
        if (!data.modified) {
            resultDiv.html('⚠️ این سفارش نیاز به اصلاح نداشت یا قبلاً اصلاح شده است.').addClass('warning').show();
            return;
        }

        var html = '<h3 style="margin-top:0;color:#4caf50;">✅ سفارش #' + data.order_id + ' با موفقیت اصلاح شد</h3>';
        html += '<p><strong>مجموع جدید:</strong> ' + data.total + '</p>';
        if (data.total_discount > 0) {
            html += '<p><strong>مجموع تخفیف:</strong> ' + formatNumber(data.total_discount) + ' تومان</p>';
        }

        html += '<table><tr><th>محصول</th><th>وضعیت</th><th>جزئیات</th></tr>';

        data.results.forEach(function (result) {
            var statusIcon = '❌';
            var statusColor = '#f44336';

            if (result.status === 'fixed') { statusIcon = '✅'; statusColor = '#4caf50'; }
            else if (result.status === 'skipped') { statusIcon = '⚠️'; statusColor = '#ff9800'; }

            html += '<tr>'
                + '<td>' + result.item_name + '</td>'
                + '<td style="color:' + statusColor + ';">' + statusIcon + '</td>'
                + '<td>' + result.message + '</td>'
                + '</tr>';
        });

        html += '</table>';
        resultDiv.html(html).addClass('success').show();
    }

    // ====== اجرای خودکار ======

    $(document).ready(function () {
        var urlParams = new URLSearchParams(window.location.search);
        var orderId = urlParams.get('fix_order');

        if (orderId) {
            $('#wdb-order-id-input').val(orderId);
            $('#wdb-fix-order-btn').trigger('click');
        }
    });
});
