// Docker serves the Front and Symfony API from the same Nginx origin.
const apiOrigin = window.location.origin;

export const apiBaseUrl = `${apiOrigin}/api`;
