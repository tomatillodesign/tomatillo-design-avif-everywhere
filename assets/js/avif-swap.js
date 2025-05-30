window.tomatilloAvifYakDelay = true;

document.addEventListener('DOMContentLoaded', function () {
	console.log('[AVIF-SWAP] Starting image replacement');

	const container = document.querySelector('.entry-content');
	if (!container) {
		console.warn('[AVIF-SWAP] No .entry-content found, aborting.');
		return;
	}

	const allImages = Array.from(container.querySelectorAll('img'));

	// Get featured images too
	const featuredWrapper = document.querySelector('.yak-featured-image-top-wrapper');
	if (featuredWrapper) {
		allImages.push(...featuredWrapper.querySelectorAll('img'));
	}

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




document.addEventListener('DOMContentLoaded', () => {
	const rotators = document.querySelectorAll('.yakstretch-image-rotator');
	if (rotators.length === 0) {
		console.log('[AVIF-SWAP] No YakStretch blocks found.');
		return;
	}

	rotators.forEach((rotator, index) => {
		const rawData = rotator.dataset.images;
		if (!rawData) return;

		let originalList;
		try {
			originalList = JSON.parse(rawData);
		} catch (e) {
			console.warn(`[AVIF-SWAP] [YakStretch #${index}] Invalid JSON in data-images`);
			return;
		}

		const rewrittenList = [];
		let pending = originalList.length;
		if (pending === 0) return;

		originalList.forEach((url, i) => {
			const baseUrl = url.replace(/-scaled(?=\.(jpe?g|png)$)/i, '');
			const avif = baseUrl.replace(/\.(jpe?g|png)$/i, '.avif');
			const webp = baseUrl.replace(/\.(jpe?g|png)$/i, '.webp');

			// Try AVIF, then WebP, then original
			fetch(avif, { method: 'HEAD' })
				.then(res => {
					rewrittenList[i] = res.ok ? avif : null;
					if (!res.ok) {
						return fetch(webp, { method: 'HEAD' }).then(res2 => {
							rewrittenList[i] = res2.ok ? webp : url;
						});
					}
				})
				.catch(() => {
					return fetch(webp, { method: 'HEAD' })
						.then(res2 => {
							rewrittenList[i] = res2.ok ? webp : url;
						})
						.catch(() => {
							rewrittenList[i] = url;
						});
				})
				.finally(() => {
					pending--;
					if (pending === 0) {
						rotator.dataset.images = JSON.stringify(rewrittenList);
						console.log(`[AVIF-SWAP] [YakStretch #${index}] Final optimized list:`, rewrittenList);

						// Preload the first image
						if (rewrittenList[0]) {
							const preload = document.createElement('link');
							preload.rel = 'preload';
							preload.as = 'image';
							preload.href = rewrittenList[0];
							document.head.appendChild(preload);
							console.log(`[AVIF-SWAP] [YakStretch #${index}] Preloading first image:`, rewrittenList[0]);
						}

						// Re-initialize the block with new images
						if (typeof yakstretchInit === 'function') {
							const wrapper = rotator.closest('.yakstretch-cover-block');
							if (wrapper) {
								// Clear existing background divs
								const existing = wrapper.querySelectorAll('.yakstretch-bg');
								existing.forEach(el => el.remove());

								// Re-run YakStretch initialization
								console.log(`[AVIF-SWAP] [YakStretch #${index}] Re-initializing with AVIF images`);
								yakstretchInit(wrapper);

								window.dispatchEvent(new Event('tomatilloAvifReady'));
								
							}
						}
					}
				});
		});
	});

	

});
