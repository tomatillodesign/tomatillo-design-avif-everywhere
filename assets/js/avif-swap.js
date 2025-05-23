document.addEventListener('DOMContentLoaded', function () {
	console.log('[AVIF-SWAP] Starting image replacement');

	const allImages = document.querySelectorAll('img');
	let processed = 0;
	let skipped = 0;

	allImages.forEach((img, index) => {
		// ✅ Already processed? Skip.
		if (img.dataset.avifSwap === 'done') return;

		const avifUrl = img.dataset.avif;
		const webpUrl = img.dataset.webp;
		const fallbackSrc = img.dataset.src || img.src;
		const fallbackSrcset = img.dataset.srcset || img.srcset || '';
		const fallbackSizes = img.dataset.sizes || img.sizes || '';
		const fallbackPriority = img.dataset.fetchpriority || img.getAttribute('fetchpriority') || '';

		if (!avifUrl && !webpUrl) {
			console.log(`[AVIF-SWAP] [#${index}] ⏩ Skipping image — no data-avif or data-webp present`);
			img.dataset.avifSwap = 'done';
			skipped++;
			return;
		}

		console.log(`[AVIF-SWAP] [#${index}] Found image:`, img);
		console.log(`[AVIF-SWAP] [#${index}] AVIF: ${avifUrl || 'none'}, WebP: ${webpUrl || 'none'}, Fallback: ${fallbackSrc}`);
		processed++;
		img.dataset.avifSwap = 'done';

		const replaceImage = (newSrc) => {
			const clone = img.cloneNode(true);
			clone.src = newSrc;

			if (newSrc !== fallbackSrc) {
				clone.removeAttribute('srcset');
				clone.removeAttribute('sizes');
				clone.removeAttribute('fetchpriority');
			} else {
				if (fallbackSrcset) clone.setAttribute('srcset', fallbackSrcset);
				if (fallbackSizes) clone.setAttribute('sizes', fallbackSizes);
				if (fallbackPriority) clone.setAttribute('fetchpriority', fallbackPriority);
			}

			console.log(`[AVIF-SWAP] [#${index}] 🖼 Replacing image with: ${newSrc}`);
			img.replaceWith(clone);
		};

		const checkFormat = (url, formatName, onSuccess, onFailure) => {
			if (!url) {
				console.log(`[AVIF-SWAP] [#${index}] No ${formatName} URL`);
				onFailure();
				return;
			}

			fetch(url, { method: 'HEAD' })
			.then((res) => {
				if (res.ok) {
					onSuccess(url);
				} else {
					console.info(`[AVIF-SWAP] File not found (HTTP ${res.status}): ${url}`);
					onFailure();
				}
			})
			.catch((err) => {
				console.info(`[AVIF-SWAP] Network error (expected for missing file): ${url}`);
				onFailure();
			});

		};

		checkFormat(avifUrl, 'AVIF',
			(successUrl) => replaceImage(successUrl),
			() => checkFormat(webpUrl, 'WebP',
				(successUrl) => replaceImage(successUrl),
				() => replaceImage(fallbackSrc)
			)
		);
	});

	console.log(`[AVIF-SWAP] Scan complete — ${processed} image(s) processed, ${skipped} skipped.`);
});
