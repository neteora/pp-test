import { useCallback, useEffect, useState } from 'react';
import { api } from '../api';
import { display } from '../time';
import PostEditor from '../components/PostEditor';
import type { AccountsResponse, Post, PostStatus, User } from '../types';

const STATUS_LABEL: Record<PostStatus, string> = {
  draft: 'Bozza', pending: 'Programmato', queued: 'In coda', publishing: 'In pubblicazione',
  published: 'Pubblicato', failed: 'Fallito',
};
const FILTERS: Array<PostStatus | ''> = ['', 'draft', 'pending', 'queued', 'publishing', 'published', 'failed'];

interface Props {
  user: User;
  goToAccounts: () => void;
}

export default function Posts({ user, goToAccounts }: Props) {
  const [items, setItems] = useState<Post[]>([]);
  const [accounts, setAccounts] = useState<AccountsResponse | null>(null);
  const [filter, setFilter] = useState<PostStatus | ''>('');
  const [editing, setEditing] = useState<'new' | Post | null>(null);
  const [error, setError] = useState('');

  const load = useCallback(
    () => api.posts(filter).then((r) => setItems(r.items)).catch((e: Error) => setError(e.message)),
    [filter],
  );

  useEffect(() => { api.accounts().then(setAccounts).catch((e: Error) => setError(e.message)); }, []);
  useEffect(() => {
    void load();
    const t = setInterval(() => void load(), 15000); // aggiorna gli stati (queued/publishing/published)
    return () => clearInterval(t);
  }, [load]);

  async function act(fn: (id: number) => Promise<unknown>, post: Post) {
    setError('');
    try {
      await fn(post.id);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Errore');
    }
    void load();
  }

  if (editing) {
    return (
      <PostEditor
        user={user}
        accounts={accounts?.items ?? []}
        post={editing === 'new' ? null : editing}
        onClose={() => { setEditing(null); void load(); }}
      />
    );
  }

  const noAccounts = accounts !== null && accounts.items.length === 0;

  return (
    <div>
      <div className="toolbar">
        <select value={filter} onChange={(e) => setFilter(e.target.value as PostStatus | '')}>
          {FILTERS.map((f) => <option key={f} value={f}>{f ? STATUS_LABEL[f] : 'Tutti gli stati'}</option>)}
        </select>
        <button className="primary" disabled={noAccounts || accounts === null} onClick={() => setEditing('new')}>Nuovo post</button>
      </div>
      {noAccounts && <p className="hint">Collega prima un account social. <button className="link" onClick={goToAccounts}>Vai agli account</button></p>}
      {error && <p className="error">{error}</p>}
      {items.length === 0 && <p className="hint">Nessun post.</p>}
      <ul className="posts">
        {items.map((p) => (
          <li key={p.id} className="card">
            <div className="row">
              <span className={`badge ${p.status}`}>{STATUS_LABEL[p.status]}</span>
              <strong>{p.platform}</strong>
              <span className="hint">{display(p.scheduledAt, user.timezone)}</span>
            </div>
            <p className="text">{p.text}</p>
            {p.lastError && <p className="error">{p.lastError}</p>}
            <div className="row actions">
              {(p.status === 'draft' || p.status === 'failed') && <button onClick={() => setEditing(p)}>Modifica</button>}
              {p.status === 'draft' && <button className="primary" onClick={() => void act(api.approve, p)}>Approva e programma</button>}
              {p.status === 'pending' && <button onClick={() => void act(api.unschedule, p)}>Annulla programmazione</button>}
              {p.status === 'failed' && <button onClick={() => void act(api.retry, p)}>Riprova</button>}
              {(p.status === 'draft' || p.status === 'failed') && (
                <button className="danger" onClick={() => window.confirm('Eliminare il post?') && void act(api.deletePost, p)}>Elimina</button>
              )}
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
}
