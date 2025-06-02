window.tomatilloAvifYakDelay = true;

document.addEventListener('DOMContentLoaded', function () {
	console.log('[AVIF-SWAP] Starting image replacement');

	const container = document.querySelector('.entry-content');
	if (!container) {
		console.warn('[AVIF-SWAP] No .entry-content found, aborting.');
		return;
	}

	const allImages = Array.from(container.querySelectorAll('img'));

	// New check to add fallback src
	document.querySelectorAll('.yak-single-info-card-outer-wrapper img').forEach((img, i) => {
		const existing = img.getAttribute('src');
		if (existing && existing.trim() !== '') return;

		const avif = img.dataset.avif;
		const webp = img.dataset.webp;
		const fallback = img.dataset.src;

		const chosen = avif || webp || fallback;

		if (chosen) {
			img.setAttribute('src', chosen);
			console.log(`[AVIF-SWAP] [Preload #${i}] Injected src="${chosen}" into`, img);
		} else {
			console.warn(`[AVIF-SWAP] [Preload #${i}] No available fallback for`, img);
		}
	});


	// Get featured images too
	const featuredWrapper = document.querySelector('.yak-featured-image-top-wrapper');
	if (featuredWrapper) {
		allImages.push(...featuredWrapper.querySelectorAll('img'));
	}

	let processed = 0;
	let skipped = 0;


	allImages.forEach((img, index) => {
		console.group(`[AVIF-SWAP] [#${index}]`);

		if (img.dataset.avifSwap === 'done') {
			console.log('⏩ Already processed, skipping.');
			console.groupEnd();
			return;
		}

		const avifUrl = img.dataset.avif;
		const webpUrl = img.dataset.webp;
		const fallbackSrc = img.dataset.src || img.getAttribute('src') || '';
		const fallbackSrcset = img.dataset.srcset || img.getAttribute('srcset') || '';
		const fallbackSizes = img.dataset.sizes || img.getAttribute('sizes') || '';
		const fallbackPriority = img.dataset.fetchpriority || img.getAttribute('fetchpriority') || '';

		console.log('🖼 Current IMG:', img);
		console.log('→ AVIF:', avifUrl || '[none]');
		console.log('→ WebP:', webpUrl || '[none]');
		console.log('→ Fallback:', fallbackSrc);

		processed++;
		img.dataset.avifSwap = 'done';

		const replaceImage = (newSrc, label) => {
			if (!newSrc) {
				console.warn('❌ No source provided for replacement, skipping.');
				return;
			}

			console.log(`🔁 Replacing image with [${label}]: ${newSrc}`);

			const oldSrc = img.getAttribute('src');
			img.setAttribute('src', newSrc);

			if (label !== 'fallback') {
				img.removeAttribute('srcset');
				img.removeAttribute('sizes');
				img.removeAttribute('fetchpriority');
			} else {
				if (fallbackSrcset) img.setAttribute('srcset', fallbackSrcset);
				if (fallbackSizes) img.setAttribute('sizes', fallbackSizes);
				if (fallbackPriority) img.setAttribute('fetchpriority', fallbackPriority);
			}

			console.log('✅ New src set:', img.src);
			setTimeout(() => {
				const width = img.naturalWidth;
				const height = img.naturalHeight;
				console.log(`🧪 Post-replacement dimensions: ${width}x${height}`);
				if (width === 0 || height === 0) {
					console.warn('⚠️ Image appears broken after swap!');
				}
			}, 100); // Delay to let browser re-evaluate image
		};

		const checkFormat = (url, formatName, onSuccess, onFailure) => {
			if (!url) {
				console.log(`⛔ No ${formatName} URL.`);
				onFailure();
				return;
			}

			console.log(`🌐 Checking ${formatName} URL via HEAD: ${url}`);
			fetch(url, { method: 'HEAD' })
				.then((res) => {
					if (res.ok) {
						console.log(`✅ ${formatName} exists (${res.status})`);
						onSuccess(url, formatName);
					} else {
						console.warn(`❌ ${formatName} NOT found (${res.status})`);
						onFailure();
					}
				})
				.catch((err) => {
					console.warn(`💥 Network error for ${formatName}:`, err);
					onFailure();
				});
		};

		checkFormat(avifUrl, 'AVIF',
			(url, format) => replaceImage(url, format),
			() => checkFormat(webpUrl, 'WebP',
				(url, format) => replaceImage(url, format),
				() => replaceImage(fallbackSrc, 'fallback')
			)
		);

		console.groupEnd();
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
