// Laufwerk-Auswahl-Handler
document.addEventListener('DOMContentLoaded', function() {
    // Laufwerk-Auswahl
    const driveSelect = document.getElementById('drive-select');
    if (driveSelect) {
        driveSelect.addEventListener('change', function() {
            const selectedValue = this.value;
            
            // Nur navigieren, wenn ein gültiger Wert ausgewählt wurde (nicht die Dummy-Option)
            if (selectedValue !== 'dummy') {
                window.location.href = baseUrl + '/?path=' + encodeURIComponent(selectedValue);
            }
        });
    }

    // Modal-Funktionalität für Dateidetails
    const modal = document.getElementById('file-details-modal');
    const closeModal = document.querySelector('.modal-close');
    const closeBtn = document.getElementById('modal-close-btn');
    const infoButtons = document.querySelectorAll('.info-button');
    
    // Funktion zum Schließen des Modals
    function hideModal() {
        modal.style.display = 'none';
        
        // EXIF-Daten zurücksetzen
        document.getElementById('exif-loading').style.display = 'block';
        document.getElementById('exif-content').style.display = 'none';
        document.getElementById('exif-error').style.display = 'none';
        
        // Vorschau zurücksetzen
        document.getElementById('image-preview-container').style.display = 'none';
        document.getElementById('image-preview').src = '';
    }
    
    // Event-Listener für das Schließen-X und den Schließen-Button
    if (closeModal) {
        closeModal.addEventListener('click', hideModal);
    }
    
    if (closeBtn) {
        closeBtn.addEventListener('click', hideModal);
    }
    
    // Schließen beim Klick außerhalb des Modal-Inhalts
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            hideModal();
        }
    });
    
    // Event-Listener für Info-Buttons
    infoButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Daten aus den data-Attributen lesen
            const path = this.getAttribute('data-path');
            const name = this.getAttribute('data-name');
            const isDir = this.getAttribute('data-is-dir') === '1';
            const size = this.getAttribute('data-size');
            const type = this.getAttribute('data-type');
            const mtime = this.getAttribute('data-mtime');
            
            // Titel des Modals setzen
            document.getElementById('modal-title').textContent = isDir ? 'Verzeichnisdetails' : 'Dateidetails';
            
            // Grundinformationen im Modal setzen
            document.getElementById('detail-name').textContent = name;
            document.getElementById('detail-path').textContent = path;
            document.getElementById('detail-type').textContent = type;
            document.getElementById('detail-mtime').textContent = mtime;
            
            // Größe anzeigen oder ausblenden je nach Typ
            const sizeRow = document.getElementById('detail-size-row');
            if (isDir) {
                sizeRow.style.display = 'none';
            } else {
                sizeRow.style.display = '';
                document.getElementById('detail-size').textContent = formatBytes(size);
            }
            
            // Bildvorschau und EXIF-Daten nur für Bilder anzeigen
            const isImage = ['JPG', 'JPEG', 'PNG', 'GIF', 'WEBP', 'TIFF'].includes(type);
            
            if (isImage) {
                // Bildvorschau anzeigen
                const previewContainer = document.getElementById('image-preview-container');
                const preview = document.getElementById('image-preview');
                
                previewContainer.style.display = 'block';
                
                // Bild laden mit der baseUrl
                preview.src = baseUrl + '/file.php?path=' + encodeURIComponent(path);
                
                // EXIF-Daten laden
                const exifContainer = document.getElementById('exif-data-container');
                exifContainer.style.display = 'block';
                
                // EXIF-Daten über AJAX laden mit der baseUrl
                fetch(baseUrl + '/get_exif.php?path=' + encodeURIComponent(path))
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        const exifLoading = document.getElementById('exif-loading');
                        const exifContent = document.getElementById('exif-content');
                        const exifError = document.getElementById('exif-error');
                        const exifTable = document.getElementById('exif-table');
                        
                        exifLoading.style.display = 'none';
                        
                        if (data.success && Object.keys(data.exif).length > 0) {
                            // EXIF-Daten in Tabelle anzeigen
                            exifTable.innerHTML = '';
                            
                            for (const [key, value] of Object.entries(data.exif)) {
                                const row = document.createElement('tr');
                                
                                const th = document.createElement('th');
                                th.textContent = key;
                                row.appendChild(th);
                                
                                const td = document.createElement('td');
                                
                                // Wenn es ein Thumbnail ist, als Bild anzeigen
                                if (key === 'thumbnail' && value) {
                                    const img = document.createElement('img');
                                    img.src = 'data:image/jpeg;base64,' + value;
                                    img.className = 'exif-thumbnail';
                                    
                                    const container = document.createElement('div');
                                    container.className = 'exif-thumbnail-container';
                                    container.appendChild(img);
                                    
                                    td.appendChild(container);
                                } else {
                                    td.textContent = value;
                                }
                                
                                row.appendChild(td);
                                exifTable.appendChild(row);
                            }
                            
                            exifContent.style.display = 'block';
                        } else {
                            // Keine EXIF-Daten gefunden
                            exifError.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching EXIF data:', error);
                        document.getElementById('exif-loading').style.display = 'none';
                        document.getElementById('exif-error').style.display = 'block';
                    });
            } else {
                // Für Nicht-Bilder keine Vorschau oder EXIF-Daten anzeigen
                document.getElementById('image-preview-container').style.display = 'none';
                document.getElementById('exif-data-container').style.display = 'none';
            }
            
            // Modal anzeigen
            modal.style.display = 'block';
        });
    });
});

// Hilfsfunktion zur Formatierung von Bytes in lesbare Größen
function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}