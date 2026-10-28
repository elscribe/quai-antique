const localHosts = new Set(["localhost", "127.0.0.1"]);
const configuredOrigin = window.QUAI_API_ORIGIN;

export const apiOrigin = (
  configuredOrigin ||
  (localHosts.has(window.location.hostname)
    ? "http://127.0.0.1:8000"
    : window.location.origin)
).replace(/\/$/, "");

export const apiBaseUrl = `${apiOrigin}/api`;
