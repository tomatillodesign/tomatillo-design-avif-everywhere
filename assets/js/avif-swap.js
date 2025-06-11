window.tomatilloAvifYakDelay = true;

window.addEventListener('load', () => {
	console.log('[AVIF-SWAP] Starting image replacement');

	const container = document.querySelector('.entry-content');
	if (!container) {
		console.warn('[AVIF-SWAP] No .entry-content found, aborting.');
		return;
	}

	const allImages = Array.from(container.querySelectorAll('img'));
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

		const originalSrc = img.getAttribute('src') || '';
		if (
			originalSrc &&
			/-(scaled|\d+x\d+)\.(webp|avif)$/i.test(originalSrc)
		) {
			console.log(`[AVIF-SWAP] 🚫 Clearing bad initial src: ${originalSrc}`);
			img.setAttribute('src', '');
		}

		const fallbackSrc = img.dataset.src || originalSrc;
		const fallbackSrcset = img.dataset.srcset || img.getAttribute('srcset') || '';
		const fallbackSizes = img.dataset.sizes || img.getAttribute('sizes') || '';
		const fallbackPriority = img.dataset.fetchpriority || img.getAttribute('fetchpriority') || '';

		const baseUrl = fallbackSrc
			.replace(/-scaled(?=\.(png|jpe?g|webp|avif)$)/i, '')
			.replace(/-\d+x\d+(?=\.(png|jpe?g|webp|avif)$)/i, '');
		const avifUrl = baseUrl.replace(/\.(png|jpe?g)$/i, '.avif');
		const webpUrl = baseUrl.replace(/\.(png|jpe?g)$/i, '.webp');

		console.log('🖼 Current IMG:', img);
		console.log('→ AVIF:', avifUrl || '[none]');
		console.log('→ WebP:', webpUrl || '[none]');
		console.log('→ Fallback:', fallbackSrc);

		processed++;
		img.dataset.avifSwap = 'done';

		const replaceImage = (newSrc, label) => {
			if (!newSrc) {
				console.warn('❌ No source provided for replacement, using fallback.');
				if (fallbackSrc) {
					replaceImage(fallbackSrc, 'fallback');
				}
				return;
			}

			console.log(img);
			console.log(`🔁 Replacing image with [${label}]: ${newSrc}`);
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
			}, 100);
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


// YakStretch logic unchanged
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
			const baseUrl = url
				.replace(/-scaled(?=\.(jpe?g|png)$)/i, '')
				.replace(/-\d+x\d+(?=\.(jpe?g|png)$)/i, '');
			const avif = baseUrl.replace(/\.(jpe?g|png)$/i, '.avif');
			const webp = baseUrl.replace(/\.(jpe?g|png)$/i, '.webp');

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

						if (rewrittenList[0]) {
							const preload = document.createElement('link');
							preload.rel = 'preload';
							preload.as = 'image';
							preload.href = rewrittenList[0];
							document.head.appendChild(preload);
							console.log(`[AVIF-SWAP] [YakStretch #${index}] Preloading first image:`, rewrittenList[0]);
						}

						if (typeof yakstretchInit === 'function') {
							const wrapper = rotator.closest('.yakstretch-cover-block');
							if (wrapper) {
								const existing = wrapper.querySelectorAll('.yakstretch-bg');
								existing.forEach(el => el.remove());

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


