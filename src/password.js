export const passwordHint = "Au moins 8 caractères, avec une minuscule, une majuscule, un chiffre et un caractère spécial (par exemple _ ou !).";
export function passwordMeetsRequirements(value) {
  const length = new TextEncoder().encode(value).length;
  return length >= 8 && length <= 128 && /[a-z]/.test(value)
    && /[A-Z]/.test(value) && /[0-9]/.test(value) && /[^a-zA-Z0-9\s]/.test(value);
}
