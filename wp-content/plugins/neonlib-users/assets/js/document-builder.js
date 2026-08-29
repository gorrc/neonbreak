(() => {
	'use strict';

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
			status.textContent = `${documents.length} document${documents.length === 1 ? '' : 's'} ready to publish.`;
			status.classList.remove('is-error');
		};

		const render = () => {
			mount.innerHTML = documents.map((document, index) => `
				<article class="neonlib-document-card" data-index="${index}">
					<label>Document ID<input data-key="id" value="${escapeHtml(document.id)}" maxlength="190" required></label>
					<label>Title<input data-key="title" value="${escapeHtml(document.title)}" maxlength="500" required></label>
					<label class="neonlib-document-content">Content<textarea data-key="content" rows="8" required>${escapeHtml(document.content)}</textarea></label>
					<div class="neonlib-document-controls">
						<button type="button" data-action="up" ${index === 0 ? 'disabled' : ''}>Up</button>
						<button type="button" data-action="down" ${index === documents.length - 1 ? 'disabled' : ''}>Down</button>
						<button class="neonlib-remove-document" type="button" data-action="remove">Remove</button>
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
				documents = parsed.map((item, index) => ({
					id: String(item.id || uniqueId(item.title || `document-${index + 1}`, index)),
					title: String(item.title || ''),
					content: String(item.content || ''),
				}));
				render();
				syncJson();
			} catch (error) {
				status.textContent = 'Invalid JSON. Fix it before publishing.';
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

		textarea.addEventListener('change', loadJson);
		form.addEventListener('submit', (event) => {
			syncJson();
			if (documents.length === 0) {
				event.preventDefault();
				status.textContent = 'Add at least one document before publishing.';
				status.classList.add('is-error');
			}
		});

		loadJson();
	});
})();
