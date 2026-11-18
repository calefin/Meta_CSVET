import Quill from 'quill';
import 'quill/dist/quill.snow.css';
import { marked } from 'marked';

/**
 * Rich text editor for course lesson content. Keeps Livewire's
 * lessonContent property in sync via a debounced $wire.set so it is
 * already up to date by the time the form is submitted (no reliance on
 * intercepting the submit event). HTML is re-sanitized server-side
 * (mews/purifier, 'lesson_content' config) before it is ever stored -
 * this client editor is for authoring convenience only, not the
 * security boundary.
 */
function mount(el, { initialHtml, onChange }) {
    const quill = new Quill(el, {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ header: [2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                ['blockquote'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['link'],
                ['clean'],
            ],
        },
    });

    if (initialHtml) {
        quill.clipboard.dangerouslyPasteHTML(initialHtml);
    }

    let debounceTimer = null;
    quill.on('text-change', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => onChange(quill.root.innerHTML), 400);
    });

    return {
        importMarkdown(markdownText) {
            const html = marked.parse(markdownText || '');
            quill.clipboard.dangerouslyPasteHTML(html);
            onChange(quill.root.innerHTML);
        },
        setHtml(html) {
            quill.setContents([]);
            if (html) {
                quill.clipboard.dangerouslyPasteHTML(html);
            }
            onChange(quill.root.innerHTML);
        },
        getHtml() {
            return quill.root.innerHTML;
        },
    };
}

window.IjoifaEditor = { mount };
