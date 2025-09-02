document.addEventListener('DOMContentLoaded', function() {
    const svgNS = "http://www.w3.org/2000/svg";
    const odontogramSVG = document.getElementById('odontogram-chart');
    const selectedToothDisplay = document.getElementById('selected-tooth-display');
    const selectedSurfaceDisplay = document.getElementById('selected-surface-display');
    const toothObservationsTextarea = document.getElementById('tooth-observations');
    const saveOdontogramRecordButton = document.getElementById('save-odontogram-record-btn');
    const odontogramGeneralNotesTextarea = document.getElementById('odontogram-general-notes');
    const messageArea = document.getElementById('odontogram-message-area'); // Para mensajes de error/éxito
    const conditionPalette = document.querySelector('.condition-palette');
    const applyToWholeToothButton = document.getElementById('apply-to-whole-tooth');
    const obsForToothNumSpan = document.getElementById('obs-for-tooth-num');
    const activeTabDisplaySpan = document.getElementById('active-tab-display');
    const notesForTabDisplaySpan = document.getElementById('notes-for-tab-display');
    const clearOdontogramTabButton = document.getElementById('clear-odontogram-tab-btn');

    // Colores para las solapas (pasados desde PHP o definidos aquí)
    const colorExistente = 'red';   // Prestación existente
    const colorARealizar = 'blue'; // Prestación a realizar

    let currentActiveTabType = 'existente'; // Por defecto
    let currentSelectedToothNumber = null;
    let currentSelectedSurfaceCode = null;
    let currentSelectedConditionCode = null; // Código de la paleta (C, OB, etc.)
    // currentSelectedColor ya no se usará para aplicar, sino que se derivará de la solapa

    // Estructura para mantener los datos de ambas solapas en JS
    let odontogramData = {
        'existente': {
            record_id: initialRecordIdExistente,
            notes: initialNotesExistente,
            teeth_data: JSON.parse(JSON.stringify(initialTeethDataExistente || {})), // Deep copy
            record_date_sql: initialRecordDateSqlExistente
        },
        'a_realizar': {
            record_id: initialRecordIdARealizar,
            notes: initialNotesARealizar,
            teeth_data: JSON.parse(JSON.stringify(initialTeethDataARealizar || {})), // Deep copy
            record_date_sql: initialRecordDateSqlARealizar
        }
    };
    
    // --- INICIALIZACIÓN Y DIBUJO DEL ODONTOGRAMA (similar a antes) ---
    const toothSize = 28;
    const centerSquareSize = toothSize / 2.5;
    const textYOffset = 12;
    const spacing = 5;
    const quadrantHSpacing = 15;
    const quadrantVSpacing = 25;
    const defaultSurfaceColorJS = '#e0e0e0'; // Usado en JS

    function drawToothSVG(toothNumber, x, y) {
        const group = document.createElementNS(svgNS, 'g');
        group.setAttribute('id', `tooth-group-${toothNumber}`);
        group.setAttribute('class', 'tooth-group');
        group.setAttribute('transform', `translate(${x},${y})`);
        group.setAttribute('data-tooth-number', toothNumber);

        const halfSize = toothSize / 2;
        const centerHalf = centerSquareSize / 2;
        let surfacesConfig = [
            { code: 'C', points: `${halfSize - centerHalf},${halfSize - centerHalf} ${halfSize + centerHalf},${halfSize - centerHalf} ${halfSize + centerHalf},${halfSize + centerHalf} ${halfSize - centerHalf},${halfSize + centerHalf}` },
            { code: 'V', points: `0,${toothSize} ${toothSize},${toothSize} ${halfSize + centerHalf},${halfSize + centerHalf} ${halfSize - centerHalf},${halfSize + centerHalf}` },
            { code: 'L', points: `0,0 ${halfSize - centerHalf},${halfSize - centerHalf} ${halfSize + centerHalf},${halfSize - centerHalf} ${toothSize},0` },
            { code: 'M', points: `0,0 ${halfSize - centerHalf},${halfSize - centerHalf} ${halfSize - centerHalf},${halfSize + centerHalf} 0,${toothSize}` },
            { code: 'D', points: `${toothSize},0 ${toothSize},${toothSize} ${halfSize + centerHalf},${halfSize + centerHalf} ${halfSize + centerHalf},${halfSize - centerHalf}` }
        ];

        const toothTypeDigit = parseInt(toothNumber.toString().slice(-1));
        const firstDigit = parseInt(toothNumber.toString().slice(0,1));
        
        surfacesConfig = surfacesConfig.map(s_conf => {
            let newCode = s_conf.code;
            if (s_conf.code === 'C') { // Central
                newCode = (toothTypeDigit >= 1 && toothTypeDigit <= 3) ? 'I' : 'O';
            } else if (s_conf.code === 'L' && (firstDigit === 1 || firstDigit === 2 || firstDigit === 5 || firstDigit === 6) ) { // Superiores (permanentes y deciduos)
                newCode = 'P';
            }
            return { ...s_conf, code: newCode };
        });

        surfacesConfig.forEach(s_conf => {
            const surfacePolygon = document.createElementNS(svgNS, 'polygon');
            surfacePolygon.setAttribute('class', 'tooth-surface');
            surfacePolygon.setAttribute('points', s_conf.points);
            surfacePolygon.setAttribute('data-surface-code', s_conf.code);
            surfacePolygon.setAttribute('data-tooth-number', toothNumber);
            surfacePolygon.addEventListener('click', handleSurfaceClick);
            group.appendChild(surfacePolygon);
        });

        const text = document.createElementNS(svgNS, 'text');
        text.setAttribute('class', 'tooth-number-text');
        text.setAttribute('x', toothSize / 2);
        text.setAttribute('y', toothSize + textYOffset);
        text.textContent = toothNumber;
        group.appendChild(text);
        odontogramSVG.appendChild(group);
    }

    function drawOdontogramBase() {
        if (!odontogramSVG) {
            console.error("SVG element 'odontogram-chart' not found.");
            return;
        }
        odontogramSVG.innerHTML = ''; // Limpiar SVG antes de redibujar
        const quadrantsFDI = { 
            upperRight: [18, 17, 16, 15, 14, 13, 12, 11], 
            upperLeft:  [21, 22, 23, 24, 25, 26, 27, 28], 
            lowerLeft:  [31, 32, 33, 34, 35, 36, 37, 38], 
            lowerRight: [48, 47, 46, 45, 44, 43, 42, 41], 
        };
        const svgViewBoxW = parseFloat(odontogramSVG.getAttribute('viewBox').split(' ')[2]);
        const totalArchWidth = (8 * toothSize) + (7 * spacing) + quadrantHSpacing + (8 * toothSize) + (7 * spacing);
        let currentX = (svgViewBoxW - totalArchWidth) / 2;
        const upperY = 30;
        const lowerY = upperY + toothSize + quadrantVSpacing + textYOffset + 5;

        quadrantsFDI.upperRight.slice().reverse().forEach(num => { drawToothSVG(num, currentX, upperY); currentX += toothSize + spacing; });
        currentX += quadrantHSpacing;
        quadrantsFDI.upperLeft.forEach(num => { drawToothSVG(num, currentX, upperY); currentX += toothSize + spacing; });
        
        currentX = (svgViewBoxW - totalArchWidth) / 2;
        quadrantsFDI.lowerRight.slice().reverse().forEach(num => { drawToothSVG(num, currentX, lowerY); currentX += toothSize + spacing; });
        currentX += quadrantHSpacing;
        quadrantsFDI.lowerLeft.forEach(num => { drawToothSVG(num, currentX, lowerY); currentX += toothSize + spacing; });
        
        const midLine = document.createElementNS(svgNS, 'line');
        midLine.setAttribute('x1', svgViewBoxW/2); midLine.setAttribute('y1', upperY - 10);
        midLine.setAttribute('x2', svgViewBoxW/2); midLine.setAttribute('y2', lowerY + toothSize + textYOffset + 5);
        midLine.setAttribute('class', 'midline');
        odontogramSVG.appendChild(midLine);
    }
    
    drawOdontogramBase(); // Dibujar la estructura base del odontograma una vez

    function handleSurfaceClick(event) {
        event.stopPropagation();
        document.querySelectorAll('.tooth-surface.selected-surface').forEach(sf => sf.classList.remove('selected-surface'));
        this.classList.add('selected-surface');
        currentSelectedToothNumber = this.getAttribute('data-tooth-number');
        currentSelectedSurfaceCode = this.getAttribute('data-surface-code');
        
        updateSelectedInfoPanel();
        toothObservationsTextarea.disabled = false;
        applyToWholeToothButton.disabled = false;
        
        const currentTabData = odontogramData[currentActiveTabType].teeth_data;
        toothObservationsTextarea.value = (currentTabData[currentSelectedToothNumber] && typeof currentTabData[currentSelectedToothNumber].obs === 'string') ? currentTabData[currentSelectedToothNumber].obs : '';

        if (currentSelectedConditionCode !== null) {
            applyConditionToSurface(currentSelectedToothNumber, currentSelectedSurfaceCode, currentSelectedConditionCode);
        }
    }
    
    function updateSelectedInfoPanel() {
        selectedToothDisplay.textContent = currentSelectedToothNumber || '-';
        selectedSurfaceDisplay.textContent = currentSelectedSurfaceCode || '-';
        obsForToothNumSpan.textContent = currentSelectedToothNumber ? `(${currentSelectedToothNumber})` : '';
    }

    function renderOdontogramForTab(tabType) {
        const dataForTab = odontogramData[tabType].teeth_data;
        const colorForTab = (tabType === 'existente') ? colorExistente : colorARealizar;

        // Reset all surfaces to default
        odontogramSVG.querySelectorAll('.tooth-surface').forEach(surfaceEl => {
            surfaceEl.style.fill = defaultSurfaceColorJS;
            surfaceEl.style.stroke = '#999';
            surfaceEl.style.strokeDasharray = 'none';
        });
        odontogramSVG.querySelectorAll('.tooth-number-text').forEach(textEl => {
            textEl.style.textDecoration = 'none';
            textEl.style.fill = '#333';
        });

        Object.keys(dataForTab).forEach(toothNum => {
            const toothData = dataForTab[toothNum];
            const toothGroup = odontogramSVG.querySelector(`#tooth-group-${toothNum}`);
            if (toothGroup && toothData) {
                if (toothData.whole) { // Si hay una condición para todo el diente
                    const conditionCode = toothData.whole;
                    const baseConditionColor = conditionColorsJS[conditionCode] || defaultSurfaceColorJS; // Color de la paleta
                    const displayColor = conditionCode === 'AUS' ? conditionColorsJS['AUS_VISUAL'] : colorForTab;

                    toothGroup.querySelectorAll('.tooth-surface').forEach(surfaceEl => {
                        surfaceEl.style.fill = displayColor;
                        surfaceEl.style.stroke = (conditionCode === 'AUS') ? '#aaa' : '#999';
                        surfaceEl.style.strokeDasharray = (conditionCode === 'AUS') ? '2,2' : 'none';
                    });
                    const toothNumberText = toothGroup.querySelector('.tooth-number-text');
                    if(toothNumberText) {
                        toothNumberText.style.textDecoration = (conditionCode === 'AUS') ? 'line-through' : 'none';
                        toothNumberText.style.fill = (conditionCode === 'AUS') ? '#aaa' : '#333';
                    }
                } else { // Aplicar condiciones por superficie
                    ['O', 'I', 'V', 'L', 'P', 'M', 'D', 'C'].forEach(surfCode => {
                        if (toothData[surfCode]) {
                            const conditionCode = toothData[surfCode];
                            const surfaceElement = toothGroup.querySelector(`.tooth-surface[data-surface-code='${surfCode}']`);
                            if (surfaceElement) {
                                // const baseConditionColor = conditionColorsJS[conditionCode] || defaultSurfaceColorJS; // Color de la paleta
                                surfaceElement.style.fill = colorForTab; // Usar color de la solapa
                            }
                        }
                    });
                }
            }
        });
    }
    
    function switchTab(targetTabType) {
        currentActiveTabType = targetTabType;
        activeTabDisplaySpan.textContent = targetTabType === 'existente' ? 'Prestación Existente' : 'Prestación a Realizar';
        notesForTabDisplaySpan.textContent = targetTabType === 'existente' ? 'Existente' : 'A Realizar';
        
        odontogramGeneralNotesTextarea.value = odontogramData[currentActiveTabType].notes;
        renderOdontogramForTab(currentActiveTabType);

        // Resetear selección actual de diente/superficie visualmente y en variables
        document.querySelectorAll('.tooth-surface.selected-surface').forEach(sf => sf.classList.remove('selected-surface'));
        currentSelectedToothNumber = null;
        currentSelectedSurfaceCode = null;
        updateSelectedInfoPanel();
        toothObservationsTextarea.value = '';
        toothObservationsTextarea.disabled = true;
        applyToWholeToothButton.disabled = true;
        
        // Actualizar el texto y valor del botón de guardar
        const recordForTab = odontogramData[currentActiveTabType];
        const buttonText = recordForTab.record_id 
            ? `Actualizar Odontograma (${recordForTab.record_date_display})` 
            : `Guardar Nuevo Odontograma (${new Date().toLocaleDateString('es-ES')})`;
        saveOdontogramRecordButton.innerHTML = `<i class="fas fa-save me-1"></i> ${buttonText}`;
        saveOdontogramRecordButton.dataset.defaultText = saveOdontogramRecordButton.innerHTML; // Actualizar default
    }

    // --- MANEJO DE PESTAÑAS ---
    const odontogramTabButtons = document.querySelectorAll('#odontogramTabs .nav-link');
    odontogramTabButtons.forEach(button => {
        button.addEventListener('shown.bs.tab', function (event) {
            const newTabType = event.target.getAttribute('data-record-type');
            switchTab(newTabType);
        });
    });

    // --- LÓGICA DE PALETA DE CONDICIONES Y APLICACIÓN ---
    if (conditionPalette) {
        conditionPalette.addEventListener('click', function(e) {
            const targetButton = e.target.closest('.condition-btn');
            if (targetButton) {
                currentSelectedConditionCode = targetButton.getAttribute('data-code'); 
                // No necesitamos currentSelectedColor de la paleta para aplicar al SVG
                
                document.querySelectorAll('.condition-palette .condition-btn.active').forEach(btn => btn.classList.remove('active', 'btn-primary', 'btn-dark'));
                targetButton.classList.add('active');
                if (currentSelectedConditionCode) { 
                    targetButton.classList.remove('btn-outline-secondary');
                    targetButton.classList.add('btn-primary');
                } else { // Botón Limpiar
                    targetButton.classList.remove('btn-outline-dark');
                    targetButton.classList.add('btn-dark');
                }
                
                if (currentSelectedToothNumber && currentSelectedSurfaceCode && currentSelectedConditionCode !== null) {
                    applyConditionToSurface(currentSelectedToothNumber, currentSelectedSurfaceCode, currentSelectedConditionCode);
                    showMessage(`Condición '${currentSelectedConditionCode || 'Limpiar'}' aplicada a ${currentActiveTabType} - ${currentSelectedToothNumber}-${currentSelectedSurfaceCode}.`, 'success');
                } else if (currentSelectedConditionCode !== null) {
                    showMessage(`Condición '${targetButton.textContent.trim() || 'Limpiar'}' seleccionada. Ahora seleccione un diente y superficie.`, 'info');
                }
            }
        });
    }

    function applyConditionToSurface(toothNum, surfaceCode, conditionCode) {
        const currentTabData = odontogramData[currentActiveTabType].teeth_data;
        if (!currentTabData[toothNum]) currentTabData[toothNum] = {};
        delete currentTabData[toothNum]['whole']; // Aplicar a superficie borra la condición "whole"

        const colorForTab = (currentActiveTabType === 'existente') ? colorExistente : colorARealizar;

        if (conditionCode === "") { 
            delete currentTabData[toothNum][surfaceCode];
        } else {
            currentTabData[toothNum][surfaceCode] = conditionCode;
        }
        
        const surfaceElement = odontogramSVG.querySelector(`#tooth-group-${toothNum} .tooth-surface[data-surface-code='${surfaceCode}']`);
        if (surfaceElement) {
            surfaceElement.style.fill = (conditionCode === "") ? defaultSurfaceColorJS : colorForTab;
            surfaceElement.style.stroke = '#999';
            surfaceElement.style.strokeDasharray = 'none';
        }
        updateWholeToothVisualFromSurfacesJS(toothNum, colorForTab);
    }

    if (applyToWholeToothButton) {
        applyToWholeToothButton.addEventListener('click', function() {
            if (currentSelectedToothNumber && currentSelectedConditionCode !== null) {
                applyConditionToWholeTooth(currentSelectedToothNumber, currentSelectedConditionCode);
                showMessage(`Condición '${currentSelectedConditionCode || 'Limpiar'}' aplicada a todo el diente ${currentSelectedToothNumber} en solapa ${currentActiveTabType}.`, 'success');
            } else if (!currentSelectedToothNumber) { 
                showMessage('Por favor, seleccione un diente primero.', 'warning');
            } else if (currentSelectedConditionCode === null) {  
                showMessage('Por favor, seleccione una condición/tratamiento de la paleta.', 'warning'); 
            }
        });
    }

    function applyConditionToWholeTooth(toothNum, conditionCode) {
        const currentTabData = odontogramData[currentActiveTabType].teeth_data;
        if (!currentTabData[toothNum]) currentTabData[toothNum] = {};
        
        const surfaceCodesToClear = ['O', 'I', 'V', 'L', 'P', 'M', 'D', 'C'];
        surfaceCodesToClear.forEach(s => delete currentTabData[toothNum][s]);
        
        const colorForTab = (currentActiveTabType === 'existente') ? colorExistente : colorARealizar;

        if (conditionCode === "") { 
            delete currentTabData[toothNum]['whole'];
        } else {
            currentTabData[toothNum]['whole'] = conditionCode;
        }

        const toothGroup = odontogramSVG.querySelector(`#tooth-group-${toothNum}`);
        if (toothGroup) {
            const surfaces = toothGroup.querySelectorAll('.tooth-surface');
            const finalFillColor = (conditionCode === "" || conditionCode === 'AUS') ? 
                                   (conditionCode === 'AUS' ? conditionColorsJS['AUS_VISUAL'] : defaultSurfaceColorJS) 
                                   : colorForTab;
            const isAusente = conditionCode === 'AUS';

            surfaces.forEach(s => { 
                s.style.fill = finalFillColor; 
                s.style.stroke = isAusente ? '#aaa' : '#999';
                s.style.strokeDasharray = isAusente ? '2,2' : 'none';
            });
            const toothNumberText = toothGroup.querySelector('.tooth-number-text');
            if (toothNumberText) {
                toothNumberText.style.textDecoration = isAusente ? 'line-through' : 'none';
                toothNumberText.style.fill = isAusente ? '#aaa' : '#333';
            }
        }
    }

    function updateWholeToothVisualFromSurfacesJS(toothNum, colorForTabToUse) {
        const currentTabData = odontogramData[currentActiveTabType].teeth_data;
        if (currentTabData[toothNum] && !currentTabData[toothNum]['whole']) {
            const toothGroup = odontogramSVG.querySelector(`#tooth-group-${toothNum}`);
            if (toothGroup) {
                const surfaceCodes = ['O', 'I', 'V', 'L', 'P', 'M', 'D', 'C'];
                surfaceCodes.forEach(sc => {
                    const surfaceElement = toothGroup.querySelector(`.tooth-surface[data-surface-code='${sc}']`);
                    if (surfaceElement) {
                        const cond = currentTabData[toothNum][sc];
                        surfaceElement.style.fill = cond ? colorForTabToUse : defaultSurfaceColorJS;
                        if (cond !== 'AUS') { // Asumimos AUS no se aplica a superficie individual con color de solapa.
                            surfaceElement.style.strokeDasharray = 'none';
                            surfaceElement.style.stroke = '#999';
                        }
                    }
                });
                const toothNumberText = toothGroup.querySelector('.tooth-number-text');
                if (toothNumberText) {
                    toothNumberText.style.textDecoration = 'none';
                    toothNumberText.style.fill = '#333';
                }
            }
        }
    }
    
    // --- GUARDADO Y OBSERVACIONES ---
    if (toothObservationsTextarea) {
        toothObservationsTextarea.addEventListener('change', function() {
            if (currentSelectedToothNumber) {
                const currentTabData = odontogramData[currentActiveTabType].teeth_data;
                if (!currentTabData[currentSelectedToothNumber]) currentTabData[currentSelectedToothNumber] = {};
                currentTabData[currentSelectedToothNumber].obs = this.value;
                showMessage(`Observación para diente ${currentSelectedToothNumber} (${currentActiveTabType}) actualizada localmente.`, 'info');
            }
        });
    }
    
    if (odontogramGeneralNotesTextarea) {
        odontogramGeneralNotesTextarea.addEventListener('input', function() {
            odontogramData[currentActiveTabType].notes = this.value;
        });
    }

    if (saveOdontogramRecordButton) {
        saveOdontogramRecordButton.dataset.defaultText = saveOdontogramRecordButton.innerHTML;
        saveOdontogramRecordButton.addEventListener('click', function() {
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Guardando...';
            if(messageArea) messageArea.innerHTML = '';

            const activeTabData = odontogramData[currentActiveTabType];
            const payload = {
                patient_id: patientIdJS,
                record_type: currentActiveTabType, // 'existente' o 'a_realizar'
                record_date: activeTabData.record_date_sql, // Usar la fecha original del registro si se está actualizando, o la de hoy si es nuevo.
                odontogram_notes: activeTabData.notes,
                teeth_data: activeTabData.teeth_data,
                existing_record_id: activeTabData.record_id // Puede ser null si es un nuevo registro para esta solapa
            };
            
            console.log("Payload to send:", JSON.stringify(payload, null, 2));

            fetch('<?php echo $path_to_root; ?>actions/save_odontogram_data.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let successMsg = '¡Odontograma (' + currentActiveTabType + ') guardado con éxito! ';
                    if(data.is_update) { 
                        successMsg += 'Registro actualizado.'; 
                    } else { 
                        successMsg += 'Nuevo registro ID: ' + data.odontogram_record_id + '.';
                        // Actualizar el record_id en nuestro JS local para la solapa guardada
                        odontogramData[currentActiveTabType].record_id = data.odontogram_record_id;
                        // También actualizamos el atributo data-record-id del botón de la pestaña
                        const tabButton = document.getElementById(`${currentActiveTabType}-tab`);
                        if (tabButton) tabButton.dataset.recordId = data.odontogram_record_id;
                    }
                    showMessage(successMsg, 'success');
                    // No recargamos la página para mantener la experiencia de solapas
                    // Pero sí actualizamos el texto del botón
                    const recordForTab = odontogramData[currentActiveTabType];
                     try {
                        const dateObjForDisplay = new Date(recordForTab.record_date_sql + 'T00:00:00'); //Asegurar que se interprete como local
                        const displayDate = dateObjForDisplay.toLocaleDateString('es-ES');
                        const buttonText = `Actualizar Odontograma (${displayDate})`;
                        saveOdontogramRecordButton.innerHTML = `<i class="fas fa-save me-1"></i> ${buttonText}`;
                        saveOdontogramRecordButton.dataset.defaultText = saveOdontogramRecordButton.innerHTML;
                    } catch(e) { /* No hacer nada si la fecha es inválida para el display */ }

                } else { 
                    showMessage('Error al guardar: ' + (data.message || 'Error desconocido.'), 'danger');
                }
            })
            .catch(error => { 
                console.error('Error AJAX:', error); 
                showMessage('Error de conexión al guardar.', 'danger'); 
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = this.dataset.defaultText || '<i class="fas fa-save me-1"></i> Guardar Odontograma Actual';
            });
        });
    }
    
    if (clearOdontogramTabButton) {
        clearOdontogramTabButton.addEventListener('click', function() {
            if (confirm(`¿Está seguro de que desea limpiar todos los datos del odontograma para la solapa "${currentActiveTabType === 'existente' ? 'Prestación Existente' : 'Prestación a Realizar'}"? Esta acción no se puede deshacer hasta que guarde.`)) {
                odontogramData[currentActiveTabType].teeth_data = {};
                odontogramData[currentActiveTabType].notes = ""; 
                odontogramGeneralNotesTextarea.value = "";
                renderOdontogramForTab(currentActiveTabType); // Redibujar con datos vacíos
                
                // Resetear selección actual
                currentSelectedToothNumber = null;
                currentSelectedSurfaceCode = null;
                updateSelectedInfoPanel();
                toothObservationsTextarea.value = '';
                toothObservationsTextarea.disabled = true;
                applyToWholeToothButton.disabled = true;
                
                showMessage(`Odontograma de la solapa "${currentActiveTabType}" limpiado localmente. Guarde para persistir los cambios.`, 'warning');
            }
        });
    }

    function showMessage(message, type = 'info', duration = 5000) {
        if(!messageArea) return;
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.role = 'alert';
        alertDiv.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'danger' || type === 'warning' ? 'exclamation-triangle' : 'info-circle')} me-2"></i>
                              ${message}
                              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
        messageArea.innerHTML = ''; // Limpiar mensajes anteriores
        messageArea.appendChild(alertDiv);
        setTimeout(() => {
            if (alertDiv.classList.contains('show')) {
                const bsAlert = bootstrap.Alert.getInstance(alertDiv) || new bootstrap.Alert(alertDiv);
                bsAlert.close();
            }
        }, duration);
    }
    
    // Inicializar la primera pestaña ("existente")
    switchTab('existente'); 
});

</script>