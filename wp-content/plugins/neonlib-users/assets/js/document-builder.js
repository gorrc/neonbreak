(() => {
	'use strict';
	const hr = document.documentElement.lang.toLowerCase().startsWith('hr');
	const text = (english, croatian) => hr ? croatian : english;

	const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (character) => ({
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#39;',
	}[character]));

	const slug = (value) => String(value)
		.toLowerCase()
		.normalize('NFD')
		.replace(/[\u0300-\u036f]/g, '')
		.replace(/[^a-z0-9._-]+/g, '-')
		.replace(/^-+|-+$/g, '')
		.slice(0, 190) || 'document';

	document.querySelectorAll('.neonlib-document-form').forEach((form) => {
		const textarea = form.querySelector('.neonlib-documents-json');
		const mount = form.querySelector('.neonlib-document-builder');
		const status = form.querySelector('.neonlib-json-status');
		const fileInput = form.querySelector('.neonlib-document-files');
		const addButton = form.querySelector('.neonlib-add-document');
		let documents = [];
		let jsonPending = false;
		const processButton = form.querySelector('.neonlib-process-json');
		const jsonFeedback = form.querySelector('.neonlib-json-feedback');
		const setEditorDisabled = (disabled) => {
			mount.querySelectorAll('input, textarea, button').forEach((control) => { control.disabled = disabled; });
			addButton.disabled = disabled;
			fileInput.disabled = disabled;
		};

		if (!textarea || !mount || !status || !fileInput || !addButton) {
			return;
		}

		const uniqueId = (base, currentIndex = -1) => {
			const root = slug(base);
			let result = root;
			let suffix = 2;
			while (documents.some((item, index) => index !== currentIndex && item.id === result)) {
				result = `${root}-${suffix++}`;
			}
			return result;
		};

		const syncJson = () => {
			textarea.value = JSON.stringify(documents, null, 2);
			status.textContent = hr ? `Broj dokumenata spremnih za objavu: ${documents.length}.` : `${documents.length} document${documents.length === 1 ? '' : 's'} ready to publish.`;
			status.classList.remove('is-error');
		};

		const render = () => {
			mount.innerHTML = documents.map((document, index) => `
				<article class="neonlib-document-card" data-index="${index}">
					<label>${text('Document ID', 'Identifikator dokumenta')}<input data-key="id" value="${escapeHtml(document.id)}" maxlength="190" required></label>
					<label>${text('Title', 'Naslov')}<input data-key="title" value="${escapeHtml(document.title)}" maxlength="500" required></label>
					<label class="neonlib-document-content">${text('Content', 'Sadržaj')}<textarea data-key="content" rows="8" required>${escapeHtml(document.content)}</textarea></label>
					<div class="neonlib-document-controls">
						<button type="button" data-action="up" ${index === 0 ? 'disabled' : ''}>${text('Up', 'Gore')}</button>
						<button type="button" data-action="down" ${index === documents.length - 1 ? 'disabled' : ''}>${text('Down', 'Dolje')}</button>
						<button class="neonlib-remove-document" type="button" data-action="remove">${text('Remove', 'Ukloni')}</button>
					</div>
				</article>
			`).join('');
		};

		const loadJson = () => {
			try {
				const parsed = JSON.parse(textarea.value);
				if (!Array.isArray(parsed)) {
					throw new Error('Documents must be a list.');
				}
				if (parsed.some((item) => !item || typeof item !== 'object' || Array.isArray(item))) throw new Error('Invalid document object');
			documents = parsed.map((item, index) => ({
					id: String(item.id || uniqueId(item.title || `document-${index + 1}`, index)),
					title: String(item.title || ''),
					content: String(item.content || ''),
				}));
				jsonPending = false;
				render();
				setEditorDisabled(false);
				syncJson();
				return true;
			} catch (error) {
				status.textContent = text('Invalid JSON. Fix it before publishing.', 'JSON nije valjan. Ispravite ga prije objave.');
				status.classList.add('is-error');
			}
		};

		mount.addEventListener('input', (event) => {
			const card = event.target.closest('[data-index]');
			const key = event.target.dataset.key;
			if (!card || !key) {
				return;
			}
			const index = Number(card.dataset.index);
			documents[index][key] = event.target.value;
			syncJson();
		});

		mount.addEventListener('click', (event) => {
			const button = event.target.closest('button[data-action]');
			if (!button) {
				return;
			}
			const index = Number(button.closest('[data-index]').dataset.index);
			const action = button.dataset.action;
			if (action === 'remove') documents.splice(index, 1);
			if (action === 'up' && index > 0) [documents[index - 1], documents[index]] = [documents[index], documents[index - 1]];
			if (action === 'down' && index < documents.length - 1) [documents[index + 1], documents[index]] = [documents[index], documents[index + 1]];
			render();
			syncJson();
		});

		addButton.addEventListener('click', () => {
			documents.push({ id: uniqueId('document'), title: '', content: '' });
			render();
			syncJson();
			mount.lastElementChild?.querySelector('input[data-key="title"]')?.focus();
		});

		fileInput.addEventListener('change', async (event) => {
			for (const file of event.target.files) {
				let content = await file.text();
				if (/\.html?$/i.test(file.name)) {
					const parsed = new DOMParser().parseFromString(content, 'text/html');
					parsed.querySelectorAll('script,style,noscript,template').forEach((node) => node.remove());
					content = (parsed.body?.innerText || parsed.body?.textContent || '').replace(/\n{3,}/g, '\n\n').trim();
				}
				const title = file.name.replace(/\.[^.]+$/, '');
				documents.push({ id: uniqueId(title), title, content });
			}
			event.target.value = '';
			render();
			syncJson();
		});

		textarea.addEventListener('input', () => {
            jsonPending = true;
            setEditorDisabled(true);
            const message = text('Click Load documents from JSON to apply your changes before submitting.', 'Kliknite Učitaj dokumente iz JSON-a kako biste primijenili izmjene prije slanja.');
            status.textContent = message;
            if (jsonFeedback) jsonFeedback.textContent = message;
        });
        processButton?.addEventListener('click', () => {
            if (loadJson()) {
                const message = text(`${documents.length} documents loaded. Review them above before submitting. Nothing has been submitted yet.`, `Učitano dokumenata: ${documents.length}. Pregledajte ih iznad prije slanja. Još ništa nije poslano.`);
                status.textContent = message;
                if (jsonFeedback) jsonFeedback.textContent = message;
            } else if (jsonFeedback) jsonFeedback.textContent = status.textContent;
        });
		form.addEventListener('submit', (event) => {
			if (jsonPending) {
				event.preventDefault();
				processButton?.focus();
				return;
			}
			syncJson();
			if (documents.length === 0) {
				event.preventDefault();
				status.textContent = text('Add at least one document before publishing.', 'Prije objave dodajte barem jedan dokument.');
				status.classList.add('is-error');
			}
		});

		loadJson();
	});
})();
