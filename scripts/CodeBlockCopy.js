// CodeBlockCopy.js
// Adds a "Copy" button to every <pre> inside a container (e.g., a post body).
// After copying, the button briefly shows "Copied!" then reverts.

const COPIED_TIMEOUT = 2000; // ms before "Copied!" goes back to "Copy"

function addCopyButton(pre) {
  // Avoid adding a second button if already enhanced
  if (pre.parentElement?.classList.contains('CodeBlockWrapper')) return;

  // Wrap the <pre> in a relative container so we can position the button
  const wrapper = document.createElement('div');
  wrapper.className = 'CodeBlockWrapper';
  pre.parentNode.insertBefore(wrapper, pre);
  wrapper.appendChild(pre);

  const button = document.createElement('button');
  button.className = 'CodeCopyButton';
  const words = Strings.for('CodeBlockCopy');
  button.textContent = words.copy || '';
  button.setAttribute('aria-label', words.copyLabel || '');
  button.setAttribute('type', 'button');

  button.addEventListener('click', () => {
    navigator.clipboard.writeText(pre.textContent)
      .then(() => {
        button.textContent = words.copied || '';
        button.classList.add('copied');
        setTimeout(() => {
          button.textContent = words.copy || '';
          button.classList.remove('copied');
        }, COPIED_TIMEOUT);
      })
      .catch(() => {
        // Clipboard API may fail (e.g., non‑HTTPS); silently ignore
      });
  });

  wrapper.appendChild(button);
}

/**
 * Enhance all <pre> elements inside a given container (e.g., a post element).
 */
export function enhanceCodeBlocks(container) {
  if (!container) return;
  container.querySelectorAll('.PostBody pre').forEach(pre => addCopyButton(pre));
}
import { Strings } from '/scripts/Strings.js';
