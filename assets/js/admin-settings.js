document.addEventListener('DOMContentLoaded', () => {
	console.log('[AVIF-ADMIN] admin-settings.js loaded');

	const scanBtn = document.getElementById('tomatillo-scan-avif-button');
	const resultsDiv = document.getElementById('tomatillo-avif-scan-results');
	const generateBtn = document.getElementById('tomatillo-generate-avif-button');
	const generateResultsDiv = document.getElementById('tomatillo-avif-generate-results');

	if (!scanBtn) {
		console.warn('[AVIF-ADMIN] Scan button not found in DOM.');
		return;
	}

	console.log('[AVIF-ADMIN] Scan button found, wiring click handler');

	scanBtn.addEventListener('click', () => {
		console.log('[AVIF-ADMIN] Scan button clicked');

		scanBtn.disabled = true;
		scanBtn.innerText = 'Scanning…';

		console.log('[AVIF-ADMIN] Sending AJAX request to:', ajaxurl);

		fetch(ajaxurl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: 'action=tomatillo_scan_avif',
		})
			.then((res) => {
				console.log('[AVIF-ADMIN] AJAX response received');
				return res.json();
			})
			.then((data) => {
				console.log('[AVIF-ADMIN] Parsed JSON:', data);
				scanBtn.disabled = false;
				scanBtn.innerText = 'Scan Media Library';

				if (data.success && data.data.missing.length > 0) {
					console.log('[AVIF-ADMIN] Missing images:', data.data.missing);

					const resultList = data.data.missing.map(item => {
						return `<li><strong>Attachment ID: ${item.id}</strong> — <em>${item.filename}</em></li>`;
					}).join('');

					resultsDiv.innerHTML = `
						<h3>🧾 Missing AVIF/WebP Files (${data.data.missing.length})</h3>
						<ul style="margin-left:1em">${resultList}</ul>
					`;

					// Enable Generate button
					if (generateBtn) {
						generateBtn.disabled = false;
						generateBtn.dataset.files = JSON.stringify(data.data.missing);
						console.log('[AVIF-ADMIN] Generate button enabled with files');
					}
				} else {
					resultsDiv.innerHTML = `
						<p style="color:green"><strong>🎉 All images already have AVIF/WebP formats.</strong></p>
					`;
					if (generateBtn) {
						generateBtn.disabled = true;
					}
				}
			})
			.catch((err) => {
				console.error('[AVIF-ADMIN] AJAX error:', err);
				scanBtn.disabled = false;
				scanBtn.innerText = 'Scan Media Library';
				resultsDiv.innerHTML = `<p style="color:red"><strong>Error scanning media library. Check console for details.</strong></p>`;
			});
	});

	// Handle generate button click (single-file processing)
    if (generateBtn) {
        generateBtn.addEventListener('click', () => {
            console.log('[AVIF-ADMIN] Generate button clicked');

            const files = JSON.parse(generateBtn.dataset.files || '[]');
            if (!files.length) {
                console.warn('[AVIF-ADMIN] No files to process.');
                return;
            }

            generateBtn.disabled = true;
            generateBtn.innerText = 'Generating...';
            generateResultsDiv.innerHTML = '<p>Starting generation...</p>';

            const progressWrapper = document.getElementById('tomatillo-progress-wrapper');
            const progressBar = document.getElementById('tomatillo-progress-bar');
            progressWrapper.style.display = 'block';
            progressBar.style.width = '0%';

            let index = 0;
            const success = [];
            const failed = [];

            const formatMb = (bytes) => {
                const mb = (bytes / 1024 / 1024).toFixed(2);
                return mb < 1 ? `${mb}` : mb;
            };

            function formatLine(item) {
                const originalMb = formatMb(item.original_size);
                const scaledMb = item.scaled_size ? formatMb(item.scaled_size) : originalMb;
                const base = `<strong>Attachment ID: ${item.id}</strong> — <em>${item.filename}</em> (${originalMb} MB original${item.scaled_size ? `, ${scaledMb} MB scaled` : ''})`;

                let avifHtml = '<span style="color:red;">AVIF: skipped</span>';
                if (item.avif) {
                    const avifMb = formatMb(item.avif.size_bytes);
                    const compareSize = item.scaled_size || item.original_size;
                    const savings = Math.round(100 - (item.avif.size_bytes / compareSize * 100));
                    avifHtml = `<span style="color:green;">AVIF: ${avifMb} MB (saved ${savings}%)</span>`;
                }

                let webpHtml = '<span style="color:red;">WebP: failed</span>';
                if (item.webp) {
                    const webpMb = formatMb(item.webp.size_bytes);
                    const compareSize = item.scaled_size || item.original_size;
                    const savings = Math.round(100 - (item.webp.size_bytes / compareSize * 100));
                    webpHtml = `<span style="color:green;">WebP: ${webpMb} MB (saved ${savings}%)</span>`;
                }

                return `<li class="clb-avif-reporting-item">${base}<br>${avifHtml}<br>${webpHtml}</li>`;
            }

            function processNext() {
                if (index >= files.length) {
                    // All done
                    progressWrapper.style.display = 'none';
                    generateBtn.innerText = 'Generate Missing AVIF/WebP Files';

                    generateResultsDiv.innerHTML = `<h3>✅ Generation Complete</h3>
                        <ul class="clb-avif-reporting-list" style="margin-left:1em">${success.join('')}</ul>
                        ${failed.length > 0 ? `<h3>❌ Failed</h3><ul style="color:red">${failed.map(msg => `<li>${msg}</li>`).join('')}</ul>` : ''}
                    `;
                    return;
                }

                const fileId = files[index].id;

                fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=tomatillo_generate_avif_single&file=' + encodeURIComponent(fileId)
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.data && data.data.success && data.data.success.length > 0) {
                        const line = formatLine(data.data.success[0]);
                        success.push(line);
                    } else {
                        failed.push(`ID ${fileId}: generation failed`);
                    }
                })
                .catch(err => {
                    console.error('[AVIF-ADMIN] Error on ID ' + fileId, err);
                    failed.push(`ID ${fileId}: AJAX error`);
                })
                .finally(() => {
                    index++;
                    const percent = Math.round((index / files.length) * 100);
                    progressBar.style.width = `${percent}%`;

                    generateResultsDiv.innerHTML = `
                        <p>Processed ${index} of ${files.length}...</p>
                        <ul style="margin-left:1em">${success.join('')}</ul>
                        ${failed.length > 0 ? `<h3>❌ Failed</h3><ul style="color:red">${failed.map(msg => `<li>${msg}</li>`).join('')}</ul>` : ''}
                    `;

                    setTimeout(processNext, 100);
                });
            }

            processNext();
        });
    }

});
