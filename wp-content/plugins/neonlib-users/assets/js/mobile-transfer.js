(() => {
	'use strict';
	const hr = document.documentElement.lang.toLowerCase().startsWith('hr');
	const text = (en, cro) => hr ? cro : en;
	document.querySelectorAll('.neonlib-transfer-live').forEach((panel) => {
		const pairing = panel.querySelector('.neonlib-transfer-pairing');
		const ready = panel.querySelector('.neonlib-transfer-ready');
		const status = panel.querySelector('.neonlib-transfer-status');
		const summary = panel.querySelector('.neonlib-transfer-summary');
		const countdown = panel.querySelector('.neonlib-transfer-countdown');
		const canvas = panel.querySelector('.neonlib-transfer-qr');
		let state = panel.dataset.status;
		let expires = Date.parse(panel.dataset.expires);
		let errors = 0;
		let pollTimer;
		let pending = false;
		let renderedMessage = '';
		const showMessage = (message) => {
			if (message !== renderedMessage) { status.textContent = message; renderedMessage = message; }
		};
		const messages = {
			WAITING: text('Waiting for your phone…', 'Čeka se povezivanje mobitela…'),
			PAIRED: text('Phone connected. Choose a collection and confirm sending in NeonLib.', 'Mobitel je povezan. U NeonLibu odaberi zbirku i potvrdi slanje.'),
			UPLOADED: text('Collection received. You can now review the documents.', 'Zbirka je primljena. Sada možeš pregledati dokumente.'),
			EXPIRED: text('The connection code has expired. Reload this page to create a new one.', 'Kod je istekao. Osvježi stranicu za novi kod.'),
			CANCELLED: text('Transfer cancelled.', 'Prijenos je otkazan.')
		};
		const update = () => {
			showMessage(messages[state] || messages.EXPIRED);
			pairing.hidden = state !== 'WAITING' && state !== 'PAIRED';
			ready.hidden = state !== 'UPLOADED';
		};
        let qrAttempts = 0;
        const drawQr = () => {
            try {
                const qr = qrcodegen.QrCode.encodeText(canvas.dataset.payload, qrcodegen.QrCode.Ecc.MEDIUM);
                const border = 4, size = qr.size + border * 2;
                const ns = 'http://www.w3.org/2000/svg';
                const svg = document.createElementNS(ns, 'svg');
                svg.setAttribute('viewBox', `0 0 ${size} ${size}`);
                svg.setAttribute('width', '240'); svg.setAttribute('height', '240');
                svg.setAttribute('aria-hidden', 'true'); svg.setAttribute('shape-rendering', 'crispEdges');
                const background = document.createElementNS(ns, 'rect');
                background.setAttribute('width', String(size)); background.setAttribute('height', String(size));
                background.setAttribute('fill', '#fff'); svg.appendChild(background);
                let path = '';
                for (let y = 0; y < qr.size; y++) for (let x = 0; x < qr.size; x++) {
                    if (qr.getModule(x, y)) path += `M${x + border},${y + border}h1v1h-1z`;
                }
                const modules = document.createElementNS(ns, 'path');
                modules.setAttribute('d', path); modules.setAttribute('fill', '#000'); svg.appendChild(modules);
                canvas.hidden = false; canvas.replaceChildren(svg);
            } catch (_) {
                canvas.hidden = false;
                canvas.textContent = text('QR code could not load. You can connect using the code above.', 'QR kod nije učitan. Možeš se povezati kodom iznad.');
                if (++qrAttempts < 4) setTimeout(drawQr, 1000);
            }
        };
        drawQr();
		update();
		const tick = () => {
			const seconds = Math.max(0, Math.ceil((expires - Date.now()) / 1000));
			if (state === 'WAITING' || state === 'PAIRED') {
				const value = `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
				if (countdown.firstChild.nodeValue !== value) countdown.firstChild.nodeValue = value;
			}
			if (!seconds && state !== 'UPLOADED') { state = 'EXPIRED'; update(); clearTimeout(pollTimer); clearInterval(clock); }
		};
		const clock = setInterval(tick, 1000);
		tick();
		const poll = async () => {
			if (pending || !['WAITING', 'PAIRED'].includes(state)) return;
			if (document.hidden) { pollTimer = setTimeout(poll, 5000); return; }
			pending = true;
			try {
				const body = new URLSearchParams({action: 'neonlib_mobile_status', nonce: panel.dataset.nonce, session_id: panel.dataset.session});
				const response = await fetch(panel.dataset.endpoint, {method: 'POST', body, credentials: 'same-origin', cache: 'no-store', signal: AbortSignal.timeout(25000)});
				const result = await response.json();
				if (!result.success) {
					if (['transfer_expired', 'transfer_not_found'].includes(result.data?.code)) { state = 'EXPIRED'; update(); return; }
					throw new Error('Status unavailable');
				}
				errors = 0; state = result.data.status; expires = Date.parse(result.data.expiresAt); update();
				if (state === 'UPLOADED') {
					summary.textContent = `${result.data.title || ''} · ${result.data.documentCount} ${text('documents', 'dokumenata')}`;
					clearInterval(clock); return;
				}
			} catch (_) {
				errors++;
				showMessage(text('Connection interrupted. Checking again shortly…', 'Veza je prekinuta. Uskoro slijedi nova provjera…'));
			} finally {
				pending = false;
				if (['WAITING', 'PAIRED'].includes(state)) pollTimer = setTimeout(poll, Math.min(30000, 5000 * Math.max(1, errors)));
			}
		};
		if (state === 'UPLOADED') clearInterval(clock);
		else pollTimer = setTimeout(poll, 5000);
		window.addEventListener('pagehide', () => { clearInterval(clock); clearTimeout(pollTimer); });
	});
})();
