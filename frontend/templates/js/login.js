/**
 * SecureScan – login.js
 * Connexion via POST /api/auth/login (LexikJWT Symfony)
 */

// Déjà connecté ? → dashboard direct
if (getToken()) window.location.href = 'dashboard.html';

async function handleLogin() {
  const email    = document.getElementById('email').value.trim();
  const password = document.getElementById('password').value;
  const errorBox = document.getElementById('login-error');
  const btnText  = document.getElementById('btn-text');
  const spinner  = document.getElementById('btn-spinner');
  const btn      = document.getElementById('btn-login');

  errorBox.textContent = '';
  errorBox.style.display = 'none';

  if (!email || !password) {
    errorBox.textContent = 'Veuillez remplir tous les champs.';
    errorBox.style.display = 'block';
    return;
  }

  btnText.style.display = 'none';
  spinner.style.display = 'block';
  btn.disabled = true;

  try {
    await Auth.login(email, password);
    window.location.href = 'dashboard.html';
  } catch (err) {
    errorBox.textContent =
      (err.message === 'Invalid credentials.' || err.message === 'Non authentifié')
        ? 'Email ou mot de passe incorrect.'
        : (err.message || 'Connexion impossible. Vérifiez vos identifiants.');
    errorBox.style.display = 'block';
  } finally {
    btnText.style.display = 'inline';
    spinner.style.display = 'none';
    btn.disabled = false;
  }
}

document.addEventListener('keydown', e => {
  if (e.key === 'Enter') handleLogin();
});