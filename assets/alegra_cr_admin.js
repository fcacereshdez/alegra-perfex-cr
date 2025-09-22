/**
 * JavaScript para la configuración centralizada de Alegra CR
 */

$(document).ready(function() {
    // Inicializar pestañas con scroll horizontal
    initHorizontalTabs();
    
    // Validaciones y controles del formulario
    initFormValidations();
    
    // Funcionalidad de pruebas
    initTestingFunctions();
    
    // Auto-guardado cada 30 segundos (opcional)
    // initAutoSave();
});

/**
 * Inicializar pestañas con scroll horizontal
 */
function initHorizontalTabs() {
    const $container = $('.horizontal-scrollable-tabs');
    const $tabs = $container.find('.horizontal-tabs');
    const $scroller = $container.find('.scroller');
    
    // Detectar si necesita scroll
    function checkScrollNeed() {
        const containerWidth = $tabs.width();
        const contentWidth = $tabs.find('.nav-tabs-horizontal')[0].scrollWidth;
        
        if (contentWidth > containerWidth) {
            $scroller.show();
        } else {
            $scroller.hide();
        }
    }
    
    // Scroll con botones
    $container.find('.arrow-left').click(function() {
        $tabs.animate({ scrollLeft: $tabs.scrollLeft() - 100 }, 200);
    });
    
    $container.find('.arrow-right').click(function() {
        $tabs.animate({ scrollLeft: $tabs.scrollLeft() + 100 }, 200);
    });
    
    // Verificar necesidad de scroll al cargar y redimensionar
    checkScrollNeed();
    $(window).resize(checkScrollNeed);
}

/**
 * Inicializar validaciones del formulario
 */
function initFormValidations() {
    // Mostrar/ocultar configuraciones de auto-transmisión
    $('#auto_transmit_enabled').change(function() {
        const $autoConfig = $('#auto-transmit-config, #additional-auto-config');
        
        if ($(this).is(':checked')) {
            $autoConfig.slideDown(300).addClass('alegra-cr-fade-in');
        } else {
            $autoConfig.slideUp(300);
            // Limpiar selecciones
            $('#auto-transmit-config input[type="checkbox"]').prop('checked', false);
        }
    });

    // Prevenir que un método esté en ambas categorías (tarjeta y efectivo)
    $(document).on('change', '.card-method-checkbox', function() {
        if ($(this).is(':checked')) {
            const methodId = $(this).val();
            $('.cash-method-checkbox[value="' + methodId + '"]').prop('checked', false);
            updatePaymentMethodsPreview();
        }
    });

    $(document).on('change', '.cash-method-checkbox', function() {
        if ($(this).is(':checked')) {
            const methodId = $(this).val();
            $('.card-method-checkbox[value="' + methodId + '"]').prop('checked', false);
            updatePaymentMethodsPreview();
        }
    });

    // Validación antes de enviar el formulario principal
    $('form[action*="settings"]').submit(function(e) {
        if (!validateAlegraSettings()) {
            e.preventDefault();
            return false;
        }
    });

    // Validación en tiempo real del email
    $('#alegra_email').on('blur', function() {
        const email = $(this).val();
        if (email && !isValidEmail(email)) {
            showFieldError($(this), 'Por favor ingrese un email válido');
        } else {
            clearFieldError($(this));
        }
    });

    // Validación del delay de auto-transmisión
    $('#auto_transmit_delay').on('input', function() {
        const value = parseInt($(this).val());
        if (value < 0 || value > 60) {
            showFieldError($(this), 'El valor debe estar entre 0 y 60 minutos');
        } else {
            clearFieldError($(this));
        }
    });
}

/**
 * Inicializar funciones de testing
 */
function initTestingFunctions() {
    // Test de conexión con Alegra
    $('#test-alegra-connection').click(function() {
        testAlegraConnection();
    });

    // Test de detección automática
    $('#test-auto-detection').click(function() {
        testAutoDetection();
    });

    // Test de configuración de pagos
    $('#test-payment-config').click(function() {
        testPaymentConfig();
    });

    // Limpiar resultados cuando se cambia de pestaña
    $('a[data-toggle="tab"]').on('shown.bs.tab', function() {
        $('#test-results').empty();
    });
}

/**
 * Test de conexión con Alegra
 */
function testAlegraConnection() {
    const $btn = $('#test-alegra-connection');
    const originalText = $btn.html();
    
    // Verificar que haya credenciales
    const email = $('#alegra_email').val();
    if (!email) {
        showAlert('warning', 'Por favor ingrese el email de Alegra antes de probar la conexión');
        return;
    }
    
    setButtonLoading($btn, 'Verificando...');
    
    $.post(admin_url + 'alegra_facturacion_cr/test_connection', function(data) {
        const $result = $('#connection-result');
        
        if (data.success) {
            $result.html('<div class="alert alert-success"><i class="fa fa-check"></i> ' + data.message + '</div>');
            showAlert('success', 'Conexión exitosa con Alegra');
        } else {
            $result.html('<div class="alert alert-danger"><i class="fa fa-times"></i> Error: ' + data.message + '</div>');
            showAlert('danger', 'Error de conexión: ' + data.message);
        }
    }, 'json').fail(function() {
        $('#connection-result').html('<div class="alert alert-danger"><i class="fa fa-times"></i> Error de comunicación con el servidor</div>');
        showAlert('danger', 'Error de comunicación con el servidor');
    }).always(function() {
        resetButton($btn, originalText);
    });
}

/**
 * Test de detección automática
 */
function testAutoDetection() {
    const $btn = $('#test-auto-detection');
    const originalText = $btn.html();
    
    setButtonLoading($btn, 'Probando...');
    
    $.get(admin_url + 'alegra_facturacion_cr/test_auto_detection', function(data) {
        if (data.success) {
            let resultHtml = '<div class="alert alert-info"><strong>Resultados de Detección Automática:</strong></div>';
            resultHtml += '<div class="alegra-cr-test-results">';
            
            data.results.forEach(function(result) {
                const badgeClass = result.detected ? 'success' : 'default';
                const icon = result.detected ? 'fa-check' : 'fa-times';
                
                resultHtml += '<div class="row" style="margin-bottom: 10px; padding: 8px; border: 1px solid #eee; border-radius: 4px;">';
                resultHtml += '<div class="col-md-8"><i class="fa ' + icon + '"></i> ' + result.item + '</div>';
                resultHtml += '<div class="col-md-4 text-right">';
                resultHtml += '<span class="label label-' + badgeClass + '">' + result.iva_rate + '% IVA</span>';
                resultHtml += '</div></div>';
            });
            
            resultHtml += '</div>';
            $('#test-results').html(resultHtml);
        } else {
            $('#test-results').html('<div class="alert alert-danger">Error en la prueba de detección</div>');
        }
    }, 'json').fail(function() {
        $('#test-results').html('<div class="alert alert-danger">Error de comunicación</div>');
    }).always(function() {
        resetButton($btn, originalText);
    });
}

/**
 * Test de configuración de pagos
 */
function testPaymentConfig() {
    const $btn = $('#test-payment-config');
    const originalText = $btn.html();
    
    setButtonLoading($btn, 'Verificando...');
    
    $.get(admin_url + 'alegra_facturacion_cr/test_auto_transmit_config', function(data) {
        if (data.success) {
            let resultHtml = '<div class="alert alert-info"><strong>Configuración Actual de Auto-transmisión:</strong></div>';
            
            // Estado general
            const statusClass = data.config.enabled ? 'success' : 'warning';
            const statusText = data.config.enabled ? 'Habilitada' : 'Deshabilitada';
            resultHtml += '<p><strong>Estado:</strong> <span class="label label-' + statusClass + '">' + statusText + '</span></p>';
            
            // Configuración médicos únicamente
            if (data.config.medical_only) {
                resultHtml += '<p><span class="label label-info">Solo servicios médicos</span></p>';
            }
            
            // Métodos de pago configurados
            resultHtml += '<p><strong>Métodos configurados:</strong></p>';
            if (data.method_names && data.method_names.length > 0) {
                resultHtml += '<div class="alegra-cr-method-list">';
                data.method_names.forEach(function(name) {
                    resultHtml += '<span class="label label-primary" style="margin-right: 5px;">' + name + '</span>';
                });
                resultHtml += '</div>';
            } else {
                resultHtml += '<span class="text-warning">Ningún método configurado</span>';
            }
            
            // Resultados del test en facturas recientes
            if (data.test_results && data.test_results.length > 0) {
                resultHtml += '<hr><strong>Simulación en facturas recientes:</strong>';
                resultHtml += '<div class="table-responsive" style="max-height: 200px; overflow-y: auto;">';
                resultHtml += '<table class="table table-condensed table-striped">';
                resultHtml += '<thead><tr><th>Factura</th><th>¿Se transmitiría?</th><th>Razón</th></tr></thead><tbody>';
                
                data.test_results.forEach(function(test) {
                    const badgeClass = test.should_transmit ? 'success' : 'default';
                    const statusText = test.should_transmit ? 'SÍ' : 'NO';
                    
                    resultHtml += '<tr>';
                    resultHtml += '<td>#' + test.invoice_id + '</td>';
                    resultHtml += '<td><span class="label label-' + badgeClass + '">' + statusText + '</span></td>';
                    resultHtml += '<td><small>' + test.reason + '</small></td>';
                    resultHtml += '</tr>';
                });
                
                resultHtml += '</tbody></table></div>';
            }
            
            $('#test-results').html(resultHtml);
        } else {
            $('#test-results').html('<div class="alert alert-danger">Error: ' + (data.error || 'Desconocido') + '</div>');
        }
    }, 'json').fail(function() {
        $('#test-results').html('<div class="alert alert-danger">Error de comunicación</div>');
    }).always(function() {
        resetButton($btn, originalText);
    });
}

/**
 * Validar configuración de Alegra antes del envío
 */
function validateAlegraSettings() {
    let isValid = true;
    const errors = [];

    // Validar email requerido
    const email = $('#alegra_email').val();
    if (!email || !isValidEmail(email)) {
        errors.push('Email de Alegra es requerido y debe ser válido');
        isValid = false;
    }

    // Si auto-transmisión está habilitada, validar métodos de pago
    if ($('#auto_transmit_enabled').is(':checked')) {
        const autoTransmitMethods = $('#auto-transmit-config input[type="checkbox"]:checked').length;
        if (autoTransmitMethods === 0) {
            errors.push('Debe seleccionar al menos un método de pago para auto-transmisión');
            isValid = false;
        }
    }

    // Validar delay
    const delay = parseInt($('#auto_transmit_delay').val());
    if (isNaN(delay) || delay < 0 || delay > 60) {
        errors.push('El retraso debe estar entre 0 y 60 minutos');
        isValid = false;
    }

    // Validar que no haya métodos duplicados entre tarjeta y efectivo
    const cardMethods = $('.card-method-checkbox:checked').map(function() { return $(this).val(); }).get();
    const cashMethods = $('.cash-method-checkbox:checked').map(function() { return $(this).val(); }).get();
    const duplicates = cardMethods.filter(value => cashMethods.includes(value));
    
    if (duplicates.length > 0) {
        errors.push('Un método de pago no puede estar en ambas categorías (tarjeta y efectivo)');
        isValid = false;
    }

    // Mostrar errores si los hay
    if (!isValid) {
        let errorHtml = '<div class="alert alert-danger alert-dismissible" role="alert">' +
            '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
            '<span aria-hidden="true">&times;</span></button>' +
            '<strong><i class="fa fa-exclamation-triangle"></i> Errores de validación:</strong><ul>';
        
        errors.forEach(function(error) {
            errorHtml += '<li>' + error + '</li>';
        });
        errorHtml += '</ul></div>';
        
        // Mostrar en la primera pestaña
        $('#alegra_credentials .row').first().prepend(errorHtml);
        
        // Cambiar a la primera pestaña
        $('a[href="#alegra_credentials"]').tab('show');
        
        // Scroll hacia arriba para mostrar el error
        $('html, body').animate({ scrollTop: 0 }, 300);
        
        // Remover el error después de 15 segundos
        setTimeout(function() {
            $('.alert-danger').fadeOut(500, function() { $(this).remove(); });
        }, 15000);
    }

    return isValid;
}

/**
 * Actualizar vista previa de métodos de pago
 */
function updatePaymentMethodsPreview() {
    const cardMethods = $('.card-method-checkbox:checked').length;
    const cashMethods = $('.cash-method-checkbox:checked').length;
    
    // Actualizar contadores si existen
    $('#card-methods-count').text(cardMethods);
    $('#cash-methods-count').text(cashMethods);
    
    // Mostrar/ocultar advertencias
    if (cardMethods === 0 && cashMethods === 0) {
        showPaymentMethodWarning('No hay métodos de pago configurados. Todos los pagos se tratarán como efectivo por defecto.');
    } else {
        hidePaymentMethodWarning();
    }
}

/**
 * Funciones auxiliares
 */
function setButtonLoading($btn, text) {
    $btn.prop('disabled', true);
    $btn.data('original-text', $btn.html());
    $btn.html('<i class="fa fa-spinner fa-spin"></i> ' + text);
}

function resetButton($btn, originalText) {
    $btn.prop('disabled', false);
    $btn.html(originalText || $btn.data('original-text'));
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function showFieldError($field, message) {
    // Remover error anterior
    clearFieldError($field);
    
    // Agregar clase de error
    $field.closest('.form-group').addClass('has-error');
    
    // Agregar mensaje de error
    $field.after('<span class="help-block text-danger"><i class="fa fa-exclamation-triangle"></i> ' + message + '</span>');
}

function clearFieldError($field) {
    $field.closest('.form-group').removeClass('has-error');
    $field.siblings('.help-block.text-danger').remove();
}

function showAlert(type, message, duration) {
    duration = duration || 5000;
    
    const alertClass = 'alert-' + type;
    const iconClass = type === 'success' ? 'fa-check' : (type === 'danger' ? 'fa-times' : 'fa-info-circle');
    
    const $alert = $('<div class="alert ' + alertClass + ' alert-dismissible alegra-cr-fade-in" role="alert">' +
        '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
        '<span aria-hidden="true">&times;</span></button>' +
        '<i class="fa ' + iconClass + '"></i> ' + message +
        '</div>');
    
    // Insertar al inicio del contenido actual
    const $activeTab = $('.tab-pane.active .row').first();
    if ($activeTab.length) {
        $activeTab.prepend($alert);
    } else {
        $('.tab-content').prepend($alert);
    }
    
    // Auto-remover después del tiempo especificado
    setTimeout(function() {
        $alert.fadeOut(500, function() { $(this).remove(); });
    }, duration);
}

function showPaymentMethodWarning(message) {
    $('#payment-method-warning').remove();
    const $warning = $('<div id="payment-method-warning" class="alert alert-warning alegra-cr-fade-in">' +
        '<i class="fa fa-exclamation-triangle"></i> ' + message + '</div>');
    $('#alegra_payment_methods .row').first().prepend($warning);
}

function hidePaymentMethodWarning() {
    $('#payment-method-warning').fadeOut(300, function() { $(this).remove(); });
}

/**
 * Auto-guardado (opcional)
 */
function initAutoSave() {
    let autoSaveInterval;
    let hasUnsavedChanges = false;
    
    // Detectar cambios en el formulario
    $('input, textarea, select').on('change input', function() {
        hasUnsavedChanges = true;
        
        // Reiniciar timer
        if (autoSaveInterval) {
            clearTimeout(autoSaveInterval);
        }
        
        // Programar auto-guardado en 30 segundos
        autoSaveInterval = setTimeout(function() {
            if (hasUnsavedChanges) {
                autoSaveSettings();
            }
        }, 30000);
    });
    
    // Advertir sobre cambios no guardados al salir
    $(window).on('beforeunload', function() {
        if (hasUnsavedChanges) {
            return 'Tiene cambios no guardados. ¿Está seguro de que desea salir?';
        }
    });
    
    // Limpiar flag cuando se guarda manualmente
    $('form[action*="settings"]').on('submit', function() {
        hasUnsavedChanges = false;
    });
}

function autoSaveSettings() {
    const formData = $('form[action*="settings"]').serialize();
    
    showAutoSaveIndicator('Guardando automáticamente...', 'info');
    
    $.post(admin_url + 'alegra_facturacion_cr/save_unified_settings', formData, function(data) {
        if (data.success) {
            showAutoSaveIndicator('Configuración guardada automáticamente', 'success');
            hasUnsavedChanges = false;
        } else {
            showAutoSaveIndicator('Error en auto-guardado', 'error');
        }
    }, 'json').fail(function() {
        showAutoSaveIndicator('Error de conexión en auto-guardado', 'error');
    });
}

function showAutoSaveIndicator(message, type) {
    type = type || 'success';
    
    // Remover indicador anterior
    $('.alegra-cr-autosave-indicator').remove();
    
    const colors = {
        success: '#5cb85c',
        info: '#5bc0de',
        error: '#d9534f'
    };
    
    const $indicator = $('<div class="alegra-cr-autosave-indicator">' +
        '<i class="fa fa-cloud"></i> ' + message + '</div>');
    
    $indicator.css({
        position: 'fixed',
        top: '20px',
        right: '20px',
        background: colors[type] || colors.success,
        color: 'white',
        padding: '10px 15px',
        borderRadius: '4px',
        fontSize: '12px',
        fontWeight: 'bold',
        zIndex: 9999,
        boxShadow: '0 2px 10px rgba(0,0,0,0.2)',
        animation: 'slideInRight 0.3s ease-out'
    });
    
    $('body').append($indicator);
    
    const duration = type === 'error' ? 5000 : 3000;
    setTimeout(function() {
        $indicator.fadeOut(500, function() { $(this).remove(); });
    }, duration);
}

/**
 * Funciones de configuración avanzada
 */
function exportSettings() {
    const $btn = $(event.target);
    const originalText = $btn.html();
    
    setButtonLoading($btn, 'Exportando...');
    
    $.get(admin_url + 'alegra_facturacion_cr/get_all_settings', function(data) {
        if (data.success) {
            const blob = new Blob([JSON.stringify(data, null, 2)], {
                type: 'application/json'
            });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'alegra_cr_settings_' + new Date().toISOString().split('T')[0] + '.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            showAlert('success', 'Configuración exportada exitosamente');
        } else {
            showAlert('danger', 'Error al exportar configuración');
        }
    }, 'json').fail(function() {
        showAlert('danger', 'Error de conexión al exportar');
    }).always(function() {
        resetButton($btn, originalText);
    });
}

function importSettings() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.json';
    
    input.onchange = function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const settings = JSON.parse(e.target.result);
                
                if (confirm('¿Está seguro de que desea importar esta configuración? Esto sobrescribirá la configuración actual.')) {
                    applyImportedSettings(settings);
                }
            } catch (error) {
                showAlert('danger', 'Error al leer el archivo: ' + error.message, 8000);
            }
        };
        reader.readAsText(file);
    };
    
    input.click();
}

function applyImportedSettings(settings) {
    let appliedCount = 0;
    
    try {
        // Aplicar configuraciones generales
        if (settings.general_settings) {
            Object.keys(settings.general_settings).forEach(function(key) {
                const $field = $('[name="' + key + '"]');
                if ($field.length) {
                    if ($field.is(':checkbox')) {
                        $field.prop('checked', settings.general_settings[key] == '1');
                    } else {
                        $field.val(settings.general_settings[key]);
                    }
                    appliedCount++;
                }
            });
        }
        
        // Aplicar configuración de métodos de pago
        if (settings.payment_config) {
            // Limpiar selecciones actuales
            $('.card-method-checkbox, .cash-method-checkbox').prop('checked', false);
            
            // Aplicar métodos de tarjeta
            if (settings.payment_config.card_payment_methods) {
                settings.payment_config.card_payment_methods.forEach(function(methodId) {
                    const $checkbox = $('.card-method-checkbox[value="' + methodId + '"]');
                    if ($checkbox.length) {
                        $checkbox.prop('checked', true);
                        appliedCount++;
                    }
                });
            }
            
            // Aplicar métodos de efectivo
            if (settings.payment_config.cash_payment_methods) {
                settings.payment_config.cash_payment_methods.forEach(function(methodId) {
                    const $checkbox = $('.cash-method-checkbox[value="' + methodId + '"]');
                    if ($checkbox.length) {
                        $checkbox.prop('checked', true);
                        appliedCount++;
                    }
                });
            }
        }
        
        // Trigger events para actualizar la UI
        $('#auto_transmit_enabled').trigger('change');
        updatePaymentMethodsPreview();
        
        showAlert('success', 'Configuración importada exitosamente (' + appliedCount + ' elementos aplicados)', 8000);
        
        // Marcar como cambios no guardados
        hasUnsavedChanges = true;
        
    } catch (error) {
        showAlert('danger', 'Error al aplicar la configuración importada: ' + error.message, 10000);
    }
}

/**
 * Funciones de utilidad para debugging
 */
function debugFormData() {
    const formData = {};
    $('form[action*="settings"] input, form[action*="settings"] textarea, form[action*="settings"] select').each(function() {
        const $field = $(this);
        const name = $field.attr('name');
        if (!name) return;
        
        if ($field.is(':checkbox')) {
            if (!formData[name]) formData[name] = [];
            if ($field.is(':checked')) {
                formData[name].push($field.val());
            }
        } else if ($field.is(':radio')) {
            if ($field.is(':checked')) {
                formData[name] = $field.val();
            }
        } else {
            formData[name] = $field.val();
        }
    });
    
    console.log('Form Data Debug:', formData);
    return formData;
}

/**
 * Función para resetear toda la configuración
 */
function resetAllSettings() {
    if (confirm('¿Está seguro de que desea resetear toda la configuración a los valores por defecto? Esta acción no se puede deshacer.')) {
        // Limpiar todos los campos
        $('form[action*="settings"] input[type="text"], form[action*="settings"] input[type="email"], form[action*="settings"] input[type="password"], form[action*="settings"] textarea').val('');
        $('form[action*="settings"] input[type="checkbox"]').prop('checked', false);
        $('form[action*="settings"] select').prop('selectedIndex', 0);
        
        // Aplicar valores por defecto
        $('#medical_keywords').val('consulta,examen,chequeo,revisión,diagnóstico,cirugía,operación,procedimiento,terapia,sesión,doctor,médico,especialista,evaluación');
        $('#auto_transmit_delay').val('0');
        $('#auto_detect_medical_services').prop('checked', true);
        
        // Actualizar UI
        $('#auto_transmit_enabled').trigger('change');
        updatePaymentMethodsPreview();
        
        showAlert('info', 'Configuración reseteada. No olvide guardar los cambios.', 8000);
        hasUnsavedChanges = true;
    }
}

// Funciones globales para botones de exportar/importar/reset
window.alegraExportSettings = exportSettings;
window.alegraImportSettings = importSettings;
window.alegraResetSettings = resetAllSettings;
window.alegraDebugFormData = debugFormData;

// CSS dinámico para animaciones
const style = document.createElement('style');
style.textContent = `
@keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.alegra-cr-fade-in {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
`;
document.head.appendChild(style);