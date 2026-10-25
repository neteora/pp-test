import { useCallback, useEffect, useState } from 'react';
import { api, getToken, setOnUnauthorized, setToken } from './api';
import type { AuthResponse, User } from './types';
import Login from './pages/Login';
import Accounts from './pages/Accounts';
import Posts from './pages/Posts';

type Tab = 'posts' | 'accounts';

export default function App() {
  const [user, setUser] = useState<User | null>(null);
  const [ready, setReady] = useState(false);
  const [tab, setTab] = useState<Tab>('posts');

  const logout = useCallback(() => {
    setToken(null);
    setUser(null);
  }, []);

  useEffect(() => {
    setOnUnauthorized(logout);
    if (!getToken()) {
      setReady(true);
      return;
    }
    api.me().then(setUser).catch(() => setToken(null)).finally(() => setReady(true));
  }, [logout]);

  if (!ready) return <p className="center">Caricamento…</p>;
  if (!user) {
    return (
      <Login
        onLogin={(res: AuthResponse) => {
          setToken(res.token);
          setUser(res.user);
        }}
      />
    );
  }

  return (
    <div className="app">
      <header>
        <h1>PostPickr</h1>
        <nav>
          <button className={tab === 'posts' ? 'active' : ''} onClick={() => setTab('posts')}>Post</button>
          <button className={tab === 'accounts' ? 'active' : ''} onClick={() => setTab('accounts')}>Account social</button>
        </nav>
        <div className="who">
          <span>{user.email} · {user.timezone}</span>
          <button onClick={() => api.logout().catch(() => {}).finally(logout)}>Esci</button>
        </div>
      </header>
      <main>
        {tab === 'posts' ? <Posts user={user} goToAccounts={() => setTab('accounts')} /> : <Accounts user={user} />}
      </main>
    </div>
  );
}
