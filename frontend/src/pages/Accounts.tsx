import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { api } from '../api';
import { display, fromInputValue } from '../time';
import type { AccountsResponse, Platform, SocialAccount, User } from '../types';

const LABEL: Record<Platform, string> = { instagram: 'Instagram', linkedin: 'LinkedIn', tiktok: 'TikTok', x: 'X' };

interface FormProps {
  platform: Platform;
  account?: SocialAccount;
  onSaved: () => void;
}

function AccountForm({ platform, account, onSaved }: FormProps) {
  const [externalAccountId, setExternalId] = useState(account?.externalAccountId ?? '');
  const [accessToken, setAccessToken] = useState('');
  const [expires, setExpires] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  async function save(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      await api.saveAccount(platform, { externalAccountId, accessToken, tokenExpiresAt: fromInputValue(expires) });
      setAccessToken('');
      onSaved();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Errore');
    } finally {
      setBusy(false);
    }
  }

  return (
    <form onSubmit={save} className="stack">
      <label>ID account<input value={externalAccountId} onChange={(e) => setExternalId(e.target.value)} required /></label>
      <label>Token di accesso
        <input type="password" autoComplete="off" value={accessToken} onChange={(e) => setAccessToken(e.target.value)}
          placeholder={account ? 'Incolla un nuovo token per sostituirlo' : ''} required />
      </label>
      <label>Scadenza token (opzionale)<input type="datetime-local" value={expires} onChange={(e) => setExpires(e.target.value)} /></label>
      {error && <p className="error">{error}</p>}
      <button className="primary" disabled={busy}>{account ? 'Aggiorna token' : 'Collega'}</button>
    </form>
  );
}

export default function Accounts({ user }: { user: User }) {
  const [data, setData] = useState<AccountsResponse | null>(null);
  const [error, setError] = useState('');

  const load = () => api.accounts().then(setData).catch((e: Error) => setError(e.message));
  useEffect(() => { void load(); }, []);

  if (error) return <p className="error">{error}</p>;
  if (!data) return <p>Caricamento…</p>;

  return (
    <div className="grid">
      <p className="hint">Un account per piattaforma. Il token si inserisce a mano e non viene mai mostrato di nuovo.</p>
      {data.supportedPlatforms.map((p) => {
        const account = data.items.find((a) => a.platform === p);
        return (
          <section className="card" key={p}>
            <h2>{LABEL[p] ?? p}
              {account && <span className={`badge ${account.status}`}>{account.status === 'active' ? 'attivo' : 'da riautenticare'}</span>}
            </h2>
            {account?.status === 'needs_reauth' && <p className="error">La piattaforma ha rifiutato il token: inseriscine uno nuovo.</p>}
            {account?.tokenExpiresAt && <p className="hint">Scadenza token: {display(account.tokenExpiresAt, user.timezone)}</p>}
            <AccountForm platform={p} account={account} onSaved={() => void load()} />
          </section>
        );
      })}
    </div>
  );
}
