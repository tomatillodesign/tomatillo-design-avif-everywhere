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
						return `<li>#${item.id} — ${item.filename}</li>`;
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

	// Handle generate button click
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
			generateResultsDiv.innerHTML = '<p>Generating files...</p>';

			fetch(ajaxurl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: 'action=tomatillo_generate_avif_batch&files=' + encodeURIComponent(JSON.stringify(files)),
			})
				.then(res => {
					console.log('[AVIF-ADMIN] Generation AJAX response received');
					return res.json();
				})
				.then(data => {
					console.log('[AVIF-ADMIN] Generation result:', data);
					generateBtn.disabled = false;
					generateBtn.innerText = 'Generate Missing AVIF/WebP Files';

					if (data.success && data.data.success.length > 0) {
						const output = data.data.success.map(item =>
							`<li>#${item.id} — ${item.filename} <span style="color:gray">(${item.note})</span></li>`
						).join('');
						generateResultsDiv.innerHTML = `<h3>✅ Generation Complete</h3><ul>${output}</ul>`;
					} else {
						generateResultsDiv.innerHTML = '<p style="color:red"><strong>No files were successfully generated.</strong></p>';
					}

					if (data.data.failed.length > 0) {
						const failOutput = data.data.failed.map(msg =>
							`<li style="color:red">${msg}</li>`
						).join('');
						generateResultsDiv.innerHTML += `<h3>❌ Failed</h3><ul>${failOutput}</ul>`;
					}
				})
				.catch(err => {
					console.error('[AVIF-ADMIN] Generation AJAX error:', err);
					generateBtn.disabled = false;
					generateBtn.innerText = 'Generate Missing AVIF/WebP Files';
					generateResultsDiv.innerHTML = '<p style="color:red"><strong>Error during generation. See console for details.</strong></p>';
				});
		});
	}
});
