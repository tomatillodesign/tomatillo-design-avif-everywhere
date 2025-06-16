// admin-settings.js (full updated drop-in)

document.addEventListener('DOMContentLoaded', () => {
	console.log('[AVIF-ADMIN] admin-settings.js loaded');

	const scanBtn = document.getElementById('tomatillo-scan-avif-button');
	const resultsDiv = document.getElementById('tomatillo-avif-scan-results');
	const generateBtn = document.getElementById('tomatillo-generate-avif-button');
	const generateResultsDiv = document.getElementById('tomatillo-avif-generate-results');

	if (!scanBtn) return;

	scanBtn.addEventListener('click', () => {
		scanBtn.disabled = true;
		scanBtn.innerText = 'Scanning…';

		const scanResultsWrapper = document.getElementById('tomatillo-avif-generate-results-wrapper');
		if (scanResultsWrapper) scanResultsWrapper.style.display = 'block';

		fetch(ajaxurl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: 'action=tomatillo_scan_avif',
		})
			.then(res => res.json())
			.then(data => {
				scanBtn.disabled = false;
				scanBtn.innerText = 'Scan Media Library';

				if (data.success && data.data.missing.length > 0) {
					const resultList = data.data.missing.map(item => {
						const cleanFilename = item.filename.replace(/-scaled(?=\.\w{3,4}$)/, '');
						return `<li><strong>${cleanFilename}</strong> <span style="font-size: 0.85em; color: #666;">(ID: ${item.id})</span></li>`;
					}).join('');

					resultsDiv.innerHTML = `
						<h3>🧾 Missing AVIF/WebP Files (${data.data.missing.length})</h3>
						<ul style="margin-left:1em; list-style-type:none; padding-left: 0;">${resultList}</ul>
					`;

					if (generateBtn) {
						generateBtn.disabled = false;
						generateBtn.dataset.files = JSON.stringify(data.data.missing);
					}
				} else {
					resultsDiv.innerHTML = `<p style="color:green"><strong>🎉 All images already have AVIF/WebP formats.</strong></p>`;
					if (generateBtn) generateBtn.disabled = true;
				}
			})
			.catch(err => {
				console.error('[AVIF-ADMIN] AJAX error:', err);
				scanBtn.disabled = false;
				scanBtn.innerText = 'Scan Media Library';
				resultsDiv.innerHTML = `<p style="color:red"><strong>Error scanning media library. Check console for details.</strong></p>`;
			});
	});

	if (generateBtn) {
		generateBtn.addEventListener('click', () => {
			const files = JSON.parse(generateBtn.dataset.files || '[]');
			if (!files.length) return;

			generateBtn.disabled = true;
			generateBtn.innerText = 'Generating...';

			let totalScaled = 0;
			let totalAvif = 0;
			let index = 0;
			const failed = [];

            const scanResultsToDisappear = document.getElementById('tomatillo-avif-scan-results');
            scanResultsToDisappear.style.display = 'none';

			const scoreboard = document.getElementById('tomatillo-bandwidth-scoreboard');
			const progressWrapper = document.getElementById('tomatillo-progress-wrapper');
			const progressBar = document.getElementById('tomatillo-progress-bar');
			const resultList = document.createElement('ul');
            resultList.className = 'clb-avif-reporting-list';
            resultList.style.listStyleType = 'none';
            resultList.style.paddingLeft = '0';
            resultList.style.setProperty('margin-left', '0', 'important');

			generateResultsDiv.innerHTML = `<p id="tomatillo-generation-status">Processed 0 of ${files.length}... Please be patient since this process can take some time.</p>`;
			generateResultsDiv.appendChild(resultList);

			progressWrapper.style.display = 'block';
			progressBar.style.width = '0%';
			if (scoreboard) scoreboard.style.display = 'block';

			const formatMb = (bytes) => (bytes / 1024 / 1024).toFixed(2);

			function formatLine(item) {
				const originalMb = formatMb(item.original_size);
				const scaledMb = item.scaled_size ? formatMb(item.scaled_size) : originalMb;
				const base = `<strong>${item.filename}</strong> — <em>(ID: ${item.id})</em> – ${originalMb} MB original${item.scaled_size ? `, ${scaledMb} MB scaled` : ''}`;

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

				return `${base}<br>${avifHtml}<br>${webpHtml}`;
			}

			function processNext() {
				if (index >= files.length) {
					progressWrapper.style.display = 'none';
					generateBtn.innerText = 'Generate Missing AVIF/WebP Files';
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
							const item = data.data.success[0];
							const li = document.createElement('li');
							li.className = 'clb-avif-reporting-item';
							li.innerHTML = formatLine(item);
							resultList.insertBefore(li, resultList.firstChild);

							const compareSize = item.scaled_size || item.original_size;
							totalScaled += compareSize;
							if (item.avif && item.avif.size_bytes) {
								totalAvif += item.avif.size_bytes;
							}

							if (scoreboard && totalScaled > 0 && totalAvif > 0) {
								const savedBytes = totalScaled - totalAvif;
								const savedMb = (savedBytes / 1024 / 1024).toFixed(2);
								const percent = Math.round((savedBytes / totalScaled) * 100);
								scoreboard.textContent = `💾 Bandwidth Saved: ${savedMb} MB (${percent}%)`;
							}
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
						const statusLine = document.getElementById('tomatillo-generation-status');
						if (statusLine) statusLine.textContent = `Processed ${index} of ${files.length}...`;
						setTimeout(processNext, 100);
					});
			}

			processNext();
		});
	}
});