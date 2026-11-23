(() => {
    if (document.documentElement.lang !== 'el') return;
    fetch('/php-scripts/translations.json', {credentials: 'same-origin'})
        .then(response => { if (!response.ok) throw new Error('Translation unavailable'); return response.json(); })
        .then(dictionary => {
            function translate(node) {
                if (node.nodeType === Node.TEXT_NODE) {
                    const parent = node.parentElement;
                    if (!parent || parent.closest('script,style,textarea,pre,code,input,.user-name,[data-no-translate]')) return;
                    const key = node.nodeValue.trim().replace(/\s+/g, ' ');
                    if (Object.prototype.hasOwnProperty.call(dictionary, key) && dictionary[key] !== key) {
                        node.nodeValue = node.nodeValue.replace(/\S[\s\S]*\S|\S/, dictionary[key]);
                    }
                } else if (node.nodeType === Node.ELEMENT_NODE) {
                    for (const child of node.childNodes) translate(child);
                }
            }
            translate(document.body);
            new MutationObserver(records => {
                for (const record of records) {
                    if (record.type === 'characterData') translate(record.target);
                    else for (const node of record.addedNodes) translate(node);
                }
            }).observe(document.body, {childList: true, characterData: true, subtree: true});
        }).catch(() => {});
})();
