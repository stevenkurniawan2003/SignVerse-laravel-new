/**
 * Bootstrap JS configurations for SignTech
 */
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.csrfToken = token.content;
}
