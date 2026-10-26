(function () {
    'use strict';

    var cfg = window.PosConfig || {
        baseUrl: '',
        csrfToken: '',
        taxRate: 0.12,
        currencySymbol: '\u20B1'
    };

    var TICKET_CART_KEY = 'screenbites.ticketCart';

    var cart = {
        tickets: [],
        concessions: [],
        payments: []
    };

    var activeCategory = 'all';
    var activeMethod = 'Cash';
    var isSplitMode = false;
    var ticketTimerInterval = null;

    var els = {
        search: document.getElementById('posSearch'),
        tabs: document.querySelectorAll('.pos-tab'),
        combosSection: document.getElementById('combosSection'),
        productsSection: document.getElementById('productsSection'),
        comboCards: document.querySelectorAll('.combo-card'),
        productCards: document.querySelectorAll('#productsGrid .pos-item-card'),

        posCartPanel: document.getElementById('posCartPanel'),
        ticketsSection: document.getElementById('ticketsSection'),
        ticketCount: document.getElementById('ticketCount'),
        ticketTimer: document.getElementById('ticketTimer'),
        cartTicketsList: document.getElementById('cartTicketsList'),
        orderTypeBadge: document.getElementById('orderTypeBadge'),

        cartConcessionsList: document.getElementById('cartConcessionsList'),
        cartEmptyMsg: document.getElementById('cartEmptyMsg'),
        clearCartBtn: document.getElementById('clearCartBtn'),

        cartBreakdown: document.getElementById('cartBreakdown'),
        rowTicketsSubtotal: document.getElementById('rowTicketsSubtotal'),
        ticketsSubtotal: document.getElementById('ticketsSubtotal'),
        concessionsSubtotal: document.getElementById('concessionsSubtotal'),
        vatExclusiveSubtotal: document.getElementById('vatExclusiveSubtotal'),
        vatAmount: document.getElementById('vatAmount'),
        grandTotal: document.getElementById('grandTotal'),

        cartPaymentSection: document.getElementById('cartPaymentSection'),
        splitToggleBtn: document.getElementById('splitToggleBtn'),
        splitToggleText: document.getElementById('splitToggleText'),
        tendersList: document.getElementById('tendersList'),
        methodBtns: document.querySelectorAll('.tender-method-btn'),
        cashTenderField: document.getElementById('cashTenderField'),
        tenderAmountLabel: document.getElementById('tenderAmountLabel'),
        tenderAmount: document.getElementById('tenderAmount'),
        referenceField: document.getElementById('referenceField'),
        tenderRefLabel: document.getElementById('tenderRefLabel'),
        tenderRef: document.getElementById('tenderRef'),
        quickCashRow: document.getElementById('quickCashRow'),
        addTenderBtn: document.getElementById('addTenderBtn'),

        totalTenderedRow: document.getElementById('totalTenderedRow'),
        totalTenderedLabel: document.getElementById('totalTenderedLabel'),
        totalTendered: document.getElementById('totalTendered'),
        remainingRow: document.getElementById('remainingRow'),
        remainingBalance: document.getElementById('remainingBalance'),
        changeRow: document.getElementById('changeRow'),
        changeDue: document.getElementById('changeDue'),

        submitOrderBtn: document.getElementById('submitOrderBtn'),
        checkoutMsg: document.getElementById('checkoutMsg'),

        successModal: document.getElementById('orderSuccessModal'),
        successOrderNumber: document.getElementById('successOrderNumber'),
        successTotalPaid: document.getElementById('successTotalPaid'),
        successChangeRow: document.getElementById('successChangeRow'),
        successChangeDue: document.getElementById('successChangeDue'),
        viewReceiptBtn: document.getElementById('viewReceiptBtn'),
        nextSaleBtn: document.getElementById('nextSaleBtn')
    };

    function fmt(n) {
        return Number(n || 0).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function peso(n) {
        return (cfg.currencySymbol || '\u20B1') + fmt(n);
    }

    function showMsg(text, type) {
        if (!els.checkoutMsg) return;
        els.checkoutMsg.className = 'checkout-msg ' + (type ? 'is-' + type : '');
        els.checkoutMsg.textContent = text;
        if (type === 'error') {
            els.checkoutMsg.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    function clearMsg() {
        if (!els.checkoutMsg) return;
        els.checkoutMsg.className = 'checkout-msg';
        els.checkoutMsg.textContent = '';
    }

    function loadHeldTickets() {
        try {
            var raw = sessionStorage.getItem(TICKET_CART_KEY);
            if (!raw) return;
            var parsed = JSON.parse(raw);
            if (parsed && Array.isArray(parsed.tickets)) {
                var now = Date.now();

                cart.tickets = parsed.tickets.filter(function (t) {
                    return !t.held_until || t.held_until > now;
                });
            }
        } catch (e) {
            console.error('Failed to parse ticketCart:', e);
            cart.tickets = [];
        }
    }

    function saveHeldTickets() {
        try {
            sessionStorage.setItem(TICKET_CART_KEY, JSON.stringify({ tickets: cart.tickets }));
        } catch (e) {
            console.error('Failed to save ticketCart:', e);
        }
    }

    function updateTicketTimer() {
        if (!cart.tickets.length) {
            if (ticketTimerInterval) clearInterval(ticketTimerInterval);
            if (els.ticketsSection) els.ticketsSection.style.display = 'none';
            return;
        }

        var now = Date.now();
        var earliestExpiry = null;
        cart.tickets.forEach(function (t) {
            if (t.held_until && (!earliestExpiry || t.held_until < earliestExpiry)) {
                earliestExpiry = t.held_until;
            }
        });

        if (!earliestExpiry) {
            if (els.ticketTimer) els.ticketTimer.textContent = 'Held';
            return;
        }

        var remainingSecs = Math.max(0, Math.floor((earliestExpiry - now) / 1000));
        if (remainingSecs <= 0) {
            if (els.ticketTimer) els.ticketTimer.textContent = 'Expired';

            cart.tickets = [];
            saveHeldTickets();
            renderCart();
            showMsg('Ticket hold expired and seats have been automatically released.', 'error');
            return;
        }

        var mins = Math.floor(remainingSecs / 60);
        var secs = remainingSecs % 60;
        var formatted = (mins < 10 ? '0' : '') + mins + ':' + (secs < 10 ? '0' : '') + secs;

        if (els.ticketTimer) {
            els.ticketTimer.textContent = formatted;
            if (remainingSecs <= 60) {
                els.ticketTimer.classList.add('urgent');
            } else {
                els.ticketTimer.classList.remove('urgent');
            }
        }
    }

    function filterCatalog() {
        var query = (els.search ? els.search.value : '').trim().toLowerCase();
        var visibleCombos = 0;
        var visibleProducts = 0;

        els.comboCards.forEach(function (card) {
            var cat = card.getAttribute('data-category');
            var name = card.getAttribute('data-name') || '';
            var matchesCategory = (activeCategory === 'all' || activeCategory === 'combos');
            var matchesQuery = !query || name.indexOf(query) !== -1;

            if (matchesCategory && matchesQuery) {
                card.style.display = '';
                visibleCombos++;
            } else {
                card.style.display = 'none';
            }
        });

        els.productCards.forEach(function (card) {
            var cat = card.getAttribute('data-category');
            var name = card.getAttribute('data-name') || '';
            var matchesCategory = (activeCategory === 'all' || activeCategory === cat);
            var matchesQuery = !query || name.indexOf(query) !== -1;

            if (matchesCategory && matchesQuery) {
                card.style.display = '';
                visibleProducts++;
            } else {
                card.style.display = 'none';
            }
        });

        if (els.combosSection) {
            els.combosSection.style.display = (visibleCombos > 0) ? '' : 'none';
        }
        if (els.productsSection) {
            els.productsSection.style.display = (visibleProducts > 0 || visibleCombos === 0) ? '' : 'none';
        }
    }

    if (els.search) {
        els.search.addEventListener('input', filterCatalog);
    }

    els.tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            els.tabs.forEach(function (t) {
                t.classList.remove('active');
                t.setAttribute('aria-selected', 'false');
            });
            tab.classList.add('active');
            tab.setAttribute('aria-selected', 'true');
            activeCategory = tab.getAttribute('data-category');
            filterCatalog();
        });
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-action="add-product"], [data-action="add-combo"]');
        if (!btn || btn.disabled) return;

        var action = btn.getAttribute('data-action');
        var id = parseInt(btn.getAttribute('data-id'), 10);
        var name = btn.getAttribute('data-name');
        var price = parseFloat(btn.getAttribute('data-price')) || 0;
        var maxStock = parseInt(btn.getAttribute('data-stock'), 10);
        if (isNaN(maxStock)) maxStock = 999;

        if (action === 'add-product') {
            addConcessionItem('product', id, name, price, maxStock);
        } else if (action === 'add-combo') {
            addConcessionItem('combo', id, name, price, 999);
        }
    });

    function addConcessionItem(type, id, name, price, maxStock) {
        var existing = cart.concessions.find(function (item) {
            return item.type === type && item.id === id;
        });

        if (existing) {
            if (existing.quantity >= existing.maxStock) {
                showMsg('Cannot add more. Reached maximum available stock (' + existing.maxStock + ') for ' + name, 'error');
                return;
            }
            existing.quantity += 1;
        } else {
            if (maxStock <= 0) {
                showMsg(name + ' is currently out of stock.', 'error');
                return;
            }
            cart.concessions.push({
                type: type,
                id: id,
                name: name,
                price: price,
                quantity: 1,
                maxStock: maxStock
            });
        }

        clearMsg();
        renderCart();
    }

    function calculateTotals() {
        var ticketsTotal = 0;
        cart.tickets.forEach(function (t) {
            ticketsTotal += parseFloat(t.price) || 0;
        });

        var concessionsTotal = 0;
        cart.concessions.forEach(function (c) {
            concessionsTotal += (parseFloat(c.price) || 0) * (parseInt(c.quantity, 10) || 1);
        });

        var grossTotal = ticketsTotal + concessionsTotal;
        var vatExclusive = grossTotal > 0 ? (grossTotal / (1 + (cfg.taxRate || 0.12))) : 0;
        var vat = grossTotal - vatExclusive;

        var totalPaid = 0;
        cart.payments.forEach(function (p) {
            totalPaid += parseFloat(p.amount) || 0;
        });

        var remaining = Math.max(0, grossTotal - totalPaid);
        var change = (totalPaid > grossTotal) ? (totalPaid - grossTotal) : 0;

        return {
            ticketsTotal: ticketsTotal,
            concessionsTotal: concessionsTotal,
            grossTotal: grossTotal,
            vatExclusive: vatExclusive,
            vat: vat,
            totalPaid: totalPaid,
            remaining: remaining,
            change: change
        };
    }

    function renderCart() {
        var totals = calculateTotals();

        if (els.orderTypeBadge) {
            if (cart.tickets.length && cart.concessions.length) {
                els.orderTypeBadge.textContent = 'Cinema + Snackbar Bundle';
            } else if (cart.tickets.length) {
                els.orderTypeBadge.textContent = 'Movie Ticket Reservation';
            } else {
                els.orderTypeBadge.textContent = 'Walk-in Concessions';
            }
        }

        if (cart.tickets.length > 0) {
            if (els.ticketsSection) els.ticketsSection.style.display = '';
            if (els.rowTicketsSubtotal) els.rowTicketsSubtotal.style.display = 'flex';
            if (els.ticketCount) els.ticketCount.textContent = cart.tickets.length;

            if (els.cartTicketsList) {
                els.cartTicketsList.innerHTML = cart.tickets.map(function (t, idx) {
                    return '<div class="cart-ticket-item">' +
                        '<div class="cart-ticket-info">' +
                            '<strong>' + (t.movie_title || 'Cinema Ticket') + '</strong>' +
                            '<span>' + (t.hall_name || '') + ' &bull; Seat ' + (t.seat_label || t.seat_id) + ' (' + (t.tier_name || 'Standard') + ')</span>' +
                            '<span class="cart-ticket-time">' + (t.start_time || '') + '</span>' +
                        '</div>' +
                        '<div class="cart-ticket-actions">' +
                            '<span class="cart-ticket-price">' + peso(t.price) + '</span>' +
                            '<button type="button" class="btn-remove-ticket" data-index="' + idx + '" title="Remove ticket">&times;</button>' +
                        '</div>' +
                    '</div>';
                }).join('');
            }
        } else {
            if (els.ticketsSection) els.ticketsSection.style.display = 'none';
            if (els.rowTicketsSubtotal) els.rowTicketsSubtotal.style.display = 'none';
        }

        if (cart.concessions.length > 0) {
            if (els.cartEmptyMsg) els.cartEmptyMsg.style.display = 'none';
            if (els.cartConcessionsList) {
                els.cartConcessionsList.innerHTML = cart.concessions.map(function (c, idx) {
                    var lineTotal = (c.price * c.quantity);
                    var isCombo = c.type === 'combo';
                    return '<div class="cart-item-row">' +
                        '<div class="cart-item-info">' +
                            '<span class="cart-item-tag ' + (isCombo ? 'is-combo' : '') + '">' + (isCombo ? 'Combo' : 'Item') + '</span>' +
                            '<div class="cart-item-title">' + c.name + '</div>' +
                            '<div class="cart-item-unit-price">' + peso(c.price) + ' each</div>' +
                        '</div>' +
                        '<div class="cart-item-controls">' +
                            '<div class="cart-qty-spinner">' +
                                '<button type="button" class="btn-qty" data-action="dec" data-index="' + idx + '" aria-label="Decrease">&minus;</button>' +
                                '<input type="number" class="qty-input" data-index="' + idx + '" value="' + c.quantity + '" min="1" max="' + c.maxStock + '">' +
                                '<button type="button" class="btn-qty" data-action="inc" data-index="' + idx + '" aria-label="Increase">&plus;</button>' +
                            '</div>' +
                            '<div class="cart-item-line-price">' + peso(lineTotal) + '</div>' +
                            '<button type="button" class="btn-remove-item" data-index="' + idx + '" title="Remove item">&times;</button>' +
                        '</div>' +
                    '</div>';
                }).join('');
            }
        } else {
            if (els.cartConcessionsList) els.cartConcessionsList.innerHTML = '';
            if (els.cartEmptyMsg && !cart.tickets.length) {
                els.cartEmptyMsg.style.display = 'block';
            } else if (els.cartEmptyMsg) {
                els.cartEmptyMsg.style.display = 'none';
            }
        }

        if (els.ticketsSubtotal) els.ticketsSubtotal.textContent = peso(totals.ticketsTotal);
        if (els.concessionsSubtotal) els.concessionsSubtotal.textContent = peso(totals.concessionsTotal);
        if (els.vatExclusiveSubtotal) els.vatExclusiveSubtotal.textContent = peso(totals.vatExclusive);
        if (els.vatAmount) els.vatAmount.textContent = peso(totals.vat);
        if (els.grandTotal) els.grandTotal.textContent = peso(totals.grossTotal);

        var hasItems = (cart.tickets.length > 0 || cart.concessions.length > 0);

        if (els.posCartPanel) {
            if (hasItems) {
                els.posCartPanel.classList.remove('is-empty-cart');
            } else {
                els.posCartPanel.classList.add('is-empty-cart');
            }
        }

        if (els.clearCartBtn) {
            els.clearCartBtn.disabled = !hasItems;
        }

        if (els.splitToggleBtn) {
            els.splitToggleBtn.style.display = hasItems ? 'inline-flex' : 'none';
        }

        if (!hasItems) {
            if (els.submitOrderBtn) {
                els.submitOrderBtn.disabled = true;
                els.submitOrderBtn.textContent = 'Select Items to Order';
            }
            if (els.remainingRow) els.remainingRow.style.display = 'none';
            if (els.changeRow) els.changeRow.style.display = 'none';
            if (els.totalTendered) els.totalTendered.textContent = peso(0);
            if (els.tenderAmount) els.tenderAmount.value = '';
            if (els.tenderRef) els.tenderRef.value = '';
            if (els.tendersList) els.tendersList.style.display = 'none';
            if (els.addTenderBtn) els.addTenderBtn.style.display = 'none';
            return;
        }

        if (!isSplitMode) {

            if (els.splitToggleBtn) els.splitToggleBtn.classList.remove('active');
            if (els.splitToggleText) els.splitToggleText.textContent = '⇄ Split Payment';
            if (els.addTenderBtn) els.addTenderBtn.style.display = 'none';
            if (els.tendersList) els.tendersList.style.display = 'none';

            if (activeMethod === 'Cash') {
                if (els.cashTenderField) els.cashTenderField.style.display = 'block';
                if (els.quickCashRow) els.quickCashRow.style.display = 'flex';
                if (els.referenceField) els.referenceField.style.display = 'none';
                if (els.tenderAmountLabel) els.tenderAmountLabel.textContent = 'Cash Received (' + (cfg.currencySymbol || '\u20B1') + ')';

                if (els.tenderAmount && document.activeElement !== els.tenderAmount) {
                    var curVal = parseFloat(els.tenderAmount.value);
                    if (isNaN(curVal) || curVal <= 0 || curVal < totals.grossTotal) {
                        els.tenderAmount.value = totals.grossTotal.toFixed(2);
                    }
                }

                var cashVal = parseFloat(els.tenderAmount ? els.tenderAmount.value : '') || 0;
                if (els.totalTenderedLabel) els.totalTenderedLabel.textContent = 'Cash Received:';
                if (els.totalTendered) els.totalTendered.textContent = peso(cashVal);

                if (cashVal >= totals.grossTotal) {
                    var changeAmt = Math.max(0, cashVal - totals.grossTotal);
                    if (els.changeRow) els.changeRow.style.display = 'flex';
                    if (els.changeDue) els.changeDue.textContent = peso(changeAmt);
                    if (els.remainingRow) els.remainingRow.style.display = 'none';

                    if (els.submitOrderBtn) {
                        els.submitOrderBtn.disabled = false;
                        if (changeAmt > 0) {
                            els.submitOrderBtn.textContent = 'Complete Order • Change: ' + peso(changeAmt);
                        } else {
                            els.submitOrderBtn.textContent = 'Complete Order • ' + peso(totals.grossTotal);
                        }
                    }
                } else {
                    var diff = totals.grossTotal - cashVal;
                    if (els.remainingRow) els.remainingRow.style.display = 'flex';
                    if (els.remainingBalance) els.remainingBalance.textContent = peso(diff);
                    if (els.changeRow) els.changeRow.style.display = 'none';

                    if (els.submitOrderBtn) {
                        els.submitOrderBtn.disabled = true;
                        els.submitOrderBtn.textContent = 'Need ' + peso(diff) + ' More Cash';
                    }
                }
            } else {

                if (els.cashTenderField) els.cashTenderField.style.display = 'none';
                if (els.quickCashRow) els.quickCashRow.style.display = 'none';
                if (els.referenceField) els.referenceField.style.display = 'block';
                if (els.tenderRef) {
                    els.tenderRef.placeholder = activeMethod === 'E-Wallet' ? 'e.g. GCash / Maya Ref #' : 'e.g. Card Authorization / Last 4';
                }

                if (els.totalTenderedLabel) els.totalTenderedLabel.textContent = 'Amount to Charge:';
                if (els.totalTendered) els.totalTendered.textContent = peso(totals.grossTotal);
                if (els.remainingRow) els.remainingRow.style.display = 'none';
                if (els.changeRow) els.changeRow.style.display = 'none';

                if (els.submitOrderBtn) {
                    els.submitOrderBtn.disabled = false;
                    els.submitOrderBtn.textContent = 'Pay via ' + (activeMethod === 'E-Wallet' ? 'GCash / Maya' : 'Card') + ' • ' + peso(totals.grossTotal);
                }
            }
        } else {

            if (els.splitToggleBtn) els.splitToggleBtn.classList.add('active');
            if (els.splitToggleText) els.splitToggleText.textContent = '✓ Standard Payment';
            if (els.addTenderBtn) els.addTenderBtn.style.display = 'block';
            if (els.tendersList) els.tendersList.style.display = 'flex';
            if (els.cashTenderField) els.cashTenderField.style.display = 'block';
            if (els.tenderAmountLabel) els.tenderAmountLabel.textContent = 'Tender Amount (' + (cfg.currencySymbol || '\u20B1') + ')';

            if (els.referenceField) {
                els.referenceField.style.display = (activeMethod === 'Cash') ? 'none' : 'block';
            }
            if (els.quickCashRow) {
                els.quickCashRow.style.display = (activeMethod === 'Cash') ? 'flex' : 'none';
            }

            if (els.tenderAmount && document.activeElement !== els.tenderAmount) {
                els.tenderAmount.value = totals.remaining > 0 ? totals.remaining.toFixed(2) : '';
            }

            renderSplitTendersList(totals);

            var isFullyPaid = (totals.grossTotal > 0 && totals.remaining <= 0.001);
            if (els.submitOrderBtn) {
                els.submitOrderBtn.disabled = !isFullyPaid;
                if (isFullyPaid) {
                    els.submitOrderBtn.textContent = 'Complete Order (' + cart.payments.length + ' Tenders) • ' + peso(totals.grossTotal);
                } else {
                    els.submitOrderBtn.textContent = 'Remaining Balance: ' + peso(totals.remaining);
                }
            }
        }
    }

    function renderSplitTendersList(totals) {
        if (!els.tendersList) return;

        if (cart.payments.length === 0) {
            els.tendersList.innerHTML = '<p class="tender-empty-hint">No split tenders recorded yet. Enter amount and click "+ Add This Payment Tender".</p>';
        } else {
            els.tendersList.innerHTML = cart.payments.map(function (p, idx) {
                return '<div class="tender-row">' +
                    '<div class="tender-info">' +
                        '<span class="tender-method-tag">' + p.method + '</span>' +
                        '<strong>' + peso(p.amount) + '</strong>' +
                        (p.reference ? '<span class="tender-ref-text">Ref: ' + p.reference + '</span>' : '') +
                    '</div>' +
                    '<button type="button" class="btn-remove-tender" data-index="' + idx + '" title="Remove tender">&times;</button>' +
                '</div>';
            }).join('');
        }

        if (els.totalTenderedLabel) els.totalTenderedLabel.textContent = 'Total Tendered:';
        if (els.totalTendered) els.totalTendered.textContent = peso(totals.totalPaid);

        if (els.remainingRow && els.remainingBalance) {
            if (totals.remaining > 0) {
                els.remainingRow.style.display = 'flex';
                els.remainingBalance.textContent = peso(totals.remaining);
            } else {
                els.remainingRow.style.display = 'none';
            }
        }

        if (els.changeRow && els.changeDue) {
            if (totals.change > 0) {
                els.changeRow.style.display = 'flex';
                els.changeDue.textContent = peso(totals.change);
            } else {
                els.changeRow.style.display = 'none';
            }
        }
    }

    if (els.splitToggleBtn) {
        els.splitToggleBtn.addEventListener('click', function () {
            isSplitMode = !isSplitMode;
            if (!isSplitMode) {
                cart.payments = [];
            }
            clearMsg();
            renderCart();
        });
    }

    if (els.tenderAmount) {
        els.tenderAmount.addEventListener('input', function () {
            if (!isSplitMode) {
                renderCart();
            }
        });
    }

    els.methodBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            els.methodBtns.forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            activeMethod = btn.getAttribute('data-method');
            clearMsg();
            renderCart();
        });
    });

    if (els.quickCashRow) {
        els.quickCashRow.addEventListener('click', function (e) {
            var btn = e.target.closest('.quick-cash-btn');
            if (!btn) return;
            var action = btn.getAttribute('data-quick');
            var totals = calculateTotals();

            if (action === 'exact') {
                els.tenderAmount.value = totals.grossTotal.toFixed(2);
            } else if (action === '1000') {
                els.tenderAmount.value = '1000.00';
            } else {
                var addVal = parseFloat(action) || 0;
                var currentVal = parseFloat(els.tenderAmount.value) || totals.grossTotal;
                els.tenderAmount.value = (currentVal + addVal).toFixed(2);
            }

            renderCart();
        });
    }

    if (els.addTenderBtn) {
        els.addTenderBtn.addEventListener('click', function () {
            var amount = parseFloat(els.tenderAmount.value);
            if (isNaN(amount) || amount <= 0) {
                showMsg('Please enter a valid payment tender amount greater than ₱0.00.', 'error');
                if (els.tenderAmount) els.tenderAmount.focus();
                return;
            }

            var ref = els.tenderRef ? els.tenderRef.value.trim() : '';
            if ((activeMethod === 'E-Wallet' || activeMethod === 'Card') && !ref) {
                if (!confirm(activeMethod + ' transaction is missing an approval reference number. Proceed anyway?')) {
                    if (els.tenderRef) els.tenderRef.focus();
                    return;
                }
            }

            cart.payments.push({
                method: activeMethod,
                amount: amount,
                reference: ref || null
            });

            if (els.tenderRef) els.tenderRef.value = '';
            clearMsg();
            renderCart();
        });
    }

    if (els.tendersList) {
        els.tendersList.addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-remove-tender');
            if (!btn) return;
            var idx = parseInt(btn.getAttribute('data-index'), 10);
            if (!isNaN(idx) && cart.payments[idx]) {
                cart.payments.splice(idx, 1);
                renderCart();
            }
        });
    }

    if (els.cartConcessionsList) {
        els.cartConcessionsList.addEventListener('click', function (e) {
            var target = e.target;
            var removeBtn = target.closest('.btn-remove-item');
            var qtyBtn = target.closest('.btn-qty');

            if (removeBtn) {
                var rIdx = parseInt(removeBtn.getAttribute('data-index'), 10);
                if (!isNaN(rIdx) && cart.concessions[rIdx]) {
                    cart.concessions.splice(rIdx, 1);
                    renderCart();
                }
                return;
            }

            if (qtyBtn) {
                var action = qtyBtn.getAttribute('data-action');
                var qIdx = parseInt(qtyBtn.getAttribute('data-index'), 10);
                var item = cart.concessions[qIdx];
                if (!item) return;

                if (action === 'dec') {
                    if (item.quantity > 1) {
                        item.quantity -= 1;
                    } else {
                        cart.concessions.splice(qIdx, 1);
                    }
                } else if (action === 'inc') {
                    if (item.quantity < item.maxStock) {
                        item.quantity += 1;
                    } else {
                        showMsg('Reached maximum stock for ' + item.name, 'error');
                    }
                }
                renderCart();
            }
        });

        els.cartConcessionsList.addEventListener('change', function (e) {
            var input = e.target.closest('.qty-input');
            if (!input) return;
            var idx = parseInt(input.getAttribute('data-index'), 10);
            var item = cart.concessions[idx];
            if (!item) return;

            var val = parseInt(input.value, 10);
            if (isNaN(val) || val <= 0) {
                cart.concessions.splice(idx, 1);
            } else {
                item.quantity = Math.min(val, item.maxStock);
            }
            renderCart();
        });
    }

    if (els.cartTicketsList) {
        els.cartTicketsList.addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-remove-ticket');
            if (!btn) return;
            var idx = parseInt(btn.getAttribute('data-index'), 10);
            var ticket = cart.tickets[idx];
            if (!ticket) return;

            fetch(cfg.baseUrl + '/ticketing/release-seat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': cfg.csrfToken
                },
                body: JSON.stringify({
                    screening_id: ticket.screening_id,
                    seat_id: ticket.seat_id
                })
            }).catch(function (err) {
                console.warn('Seat release request failed:', err);
            });

            cart.tickets.splice(idx, 1);
            saveHeldTickets();
            renderCart();
        });
    }

    if (els.clearCartBtn) {
        els.clearCartBtn.addEventListener('click', function () {
            if (!cart.tickets.length && !cart.concessions.length && !cart.payments.length) return;
            if (!confirm('Clear all concessions and held tickets in this order?')) return;

            cart.tickets.forEach(function (ticket) {
                fetch(cfg.baseUrl + '/ticketing/release-seat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': cfg.csrfToken
                    },
                    body: JSON.stringify({
                        screening_id: ticket.screening_id,
                        seat_id: ticket.seat_id
                    })
                }).catch(function () {});
            });

            cart.tickets = [];
            cart.concessions = [];
            cart.payments = [];
            saveHeldTickets();
            clearMsg();
            renderCart();
        });
    }

    if (els.submitOrderBtn) {
        els.submitOrderBtn.addEventListener('click', function () {
            var totals = calculateTotals();
            if (!cart.tickets.length && !cart.concessions.length) {
                showMsg('Your order cart is empty. Add items to order.', 'error');
                return;
            }

            var paymentsPayload = [];

            if (!isSplitMode) {

                if (activeMethod === 'Cash') {
                    var cashVal = parseFloat(els.tenderAmount ? els.tenderAmount.value : '') || totals.grossTotal;
                    if (cashVal < totals.grossTotal) {
                        showMsg('Cash received cannot be less than total amount due of ' + peso(totals.grossTotal), 'error');
                        if (els.tenderAmount) els.tenderAmount.focus();
                        return;
                    }
                    paymentsPayload.push({
                        method: 'Cash',
                        amount: cashVal,
                        reference: null
                    });
                } else {
                    var ref = els.tenderRef ? els.tenderRef.value.trim() : '';
                    if (!ref && (activeMethod === 'E-Wallet' || activeMethod === 'Card')) {
                        if (!confirm(activeMethod + ' transaction is missing an approval reference number. Proceed anyway?')) {
                            if (els.tenderRef) els.tenderRef.focus();
                            return;
                        }
                    }
                    paymentsPayload.push({
                        method: activeMethod,
                        amount: totals.grossTotal,
                        reference: ref || null
                    });
                }
            } else {

                if (totals.remaining > 0.001) {
                    showMsg('Payment incomplete. Outstanding balance of ' + peso(totals.remaining) + ' must be tendered.', 'error');
                    return;
                }
                paymentsPayload = cart.payments.map(function (p) {
                    return {
                        method: p.method,
                        amount: parseFloat(p.amount),
                        reference: p.reference || null
                    };
                });
            }

            els.submitOrderBtn.disabled = true;
            els.submitOrderBtn.textContent = 'Processing Transaction...';
            clearMsg();

            var payload = {
                tickets: cart.tickets.map(function (t) {
                    return {
                        screening_id: t.screening_id,
                        seat_id: t.seat_id,
                        price: parseFloat(t.price)
                    };
                }),
                concessions: cart.concessions.map(function (c) {
                    return {
                        type: c.type,
                        id: c.id,
                        quantity: c.quantity,
                        price: parseFloat(c.price)
                    };
                }),
                payments: paymentsPayload
            };

            fetch(cfg.baseUrl + '/checkout/process', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': cfg.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, status: res.status, data: data };
                });
            })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    throw new Error(result.data.message || 'Transaction failed. Status: ' + result.status);
                }

                var data = result.data;

                cart.tickets = [];
                cart.concessions = [];
                cart.payments = [];
                saveHeldTickets();
                renderCart();

                if (els.successOrderNumber) els.successOrderNumber.textContent = data.order_number;
                if (els.successTotalPaid) els.successTotalPaid.textContent = peso(totals.grossTotal);

                if (els.successChangeRow && els.successChangeDue) {
                    if (data.change_due > 0) {
                        els.successChangeRow.style.display = 'flex';
                        els.successChangeDue.textContent = peso(data.change_due);
                    } else {
                        els.successChangeRow.style.display = 'none';
                    }
                }

                if (els.viewReceiptBtn) {
                    els.viewReceiptBtn.href = data.receipt_url || (cfg.baseUrl + '/receipt/' + data.order_number);
                }

                if (els.successModal && typeof els.successModal.showModal === 'function') {
                    els.successModal.showModal();
                } else {
                    alert('Order ' + data.order_number + ' completed! Change Due: ' + peso(data.change_due));
                    window.location.href = data.receipt_url || (cfg.baseUrl + '/receipt/' + data.order_number);
                }
            })
            .catch(function (err) {
                console.error('Checkout error:', err);
                showMsg(err.message || 'Transaction could not be completed. Please review items and payment tender.', 'error');
                els.submitOrderBtn.disabled = false;
                renderCart();
            });
        });
    }

    if (els.nextSaleBtn && els.successModal) {
        els.nextSaleBtn.addEventListener('click', function () {
            els.successModal.close();
            window.location.reload();
        });
    }

    loadHeldTickets();
    renderCart();
    filterCatalog();

    if (cart.tickets.length > 0) {
        updateTicketTimer();
        ticketTimerInterval = setInterval(updateTicketTimer, 1000);
    }
})();
