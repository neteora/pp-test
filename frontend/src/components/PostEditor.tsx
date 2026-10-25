import { useEffect, useRef, useState } from 'react';
import type { ChangeEvent } from 'react';
import { api } from '../api';
import { fromInputValue, toInputValue } from '../time';
import Preview from './Preview';
import type { Media, Post, PostInput, SocialAccount, User } from '../types';

interface Props {
  user: User;
  accounts: SocialAccount[];
  post: Post | null;
  onClose: () => void;
}

export default function PostEditor({ user, accounts, post, onClose }: Props) {
  const [accountId, setAccountId] = useState<number>(post?.socialAccountId ?? accounts[0]?.id ?? 0);
  const [text, setText] = useState(post?.text ?? '');
  const [media, setMedia] = useState<Media | null>(post?.media ?? null);
  const [scheduled, setScheduled] = useState(toInputValue(post?.scheduledAt, user.timezone));
  const [generationId, setGenerationId] = useState<number | null>(null);
  const [prompt, setPrompt] = useState('');
  const [generating, setGenerating] = useState(false);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const poll = useRef<ReturnType<typeof setTimeout>>();

  useEffect(() => () => clearTimeout(poll.current), []);

  const account = accounts.find((a) => a.id === accountId);
  const message = (e: unknown) => (e instanceof Error ? e.message : 'Errore');

  async function generate() {
    setError('');
    setGenerating(true);
    try {
      const g = await api.generate({ text: prompt });
      setGenerationId(g.id);
      const tick = async () => {
        try {
          const r = await api.generation(g.id);
          if (r.status === 'done') {
            // Il testo generato è solo una bozza: l'utente lo rivede e lo approva
            setText(r.resultText ?? '');
            setGenerating(false);
          } else if (r.status === 'failed') {
            setError(`Generazione fallita: ${r.error ?? 'errore'}`);
            setGenerating(false);
          } else {
            poll.current = setTimeout(() => void tick(), 2000);
          }
        } catch (e) {
          setError(message(e));
          setGenerating(false);
        }
      };
      poll.current = setTimeout(() => void tick(), 1500);
    } catch (e) {
      setError(message(e));
      setGenerating(false);
    }
  }

  async function upload(e: ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;
    setError('');
    try {
      setMedia(await api.uploadMedia(file));
    } catch (err) {
      setError(message(err));
    }
  }

  async function save(approve: boolean) {
    setBusy(true);
    setError('');
    try {
      const body: PostInput = {
        socialAccountId: accountId,
        text,
        mediaAssetId: media?.id ?? null,
        scheduledAt: fromInputValue(scheduled),
      };
      if (generationId !== null) body.generationRequestId = generationId;
      const saved = post ? await api.updatePost(post.id, body) : await api.createPost(body);
      if (approve) await api.approve(saved.id);
      onClose();
    } catch (err) {
      setError(message(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="editor">
      <div className="stack">
        <h2>{post ? 'Modifica post' : 'Nuovo post'}</h2>

        <label>Account
          <select value={accountId} onChange={(e) => setAccountId(Number(e.target.value))}>
            {accounts.map((a) => <option key={a.id} value={a.id}>{a.platform}{a.status !== 'active' ? ' (da riautenticare)' : ''}</option>)}
          </select>
        </label>

        <fieldset>
          <legend>Genera il testo con l'AI (opzionale)</legend>
          <textarea rows={3} value={prompt} onChange={(e) => setPrompt(e.target.value)}
            placeholder="Descrivi cosa vuoi scrivere. Il testo viene inviato al provider così com'è." />
          <button type="button" onClick={() => void generate()} disabled={generating || !prompt.trim()}>
            {generating ? 'Generazione in corso…' : 'Genera bozza'}
          </button>
        </fieldset>

        <label>Testo
          <textarea rows={8} value={text} onChange={(e) => setText(e.target.value)} />
        </label>

        <label>Media
          <input type="file" accept="image/jpeg,image/png,video/mp4" onChange={(e) => void upload(e)} />
        </label>
        {media && <button type="button" className="link" onClick={() => setMedia(null)}>Rimuovi media</button>}

        <label>Pubblica il ({user.timezone})
          <input type="datetime-local" value={scheduled} onChange={(e) => setScheduled(e.target.value)} />
        </label>

        {error && <p className="error">{error}</p>}
        <div className="row actions">
          <button onClick={onClose} disabled={busy}>Annulla</button>
          <button onClick={() => void save(false)} disabled={busy || !text.trim()}>Salva bozza</button>
          <button className="primary" onClick={() => void save(true)} disabled={busy || !text.trim() || !scheduled}>
            Salva e approva
          </button>
        </div>
        <p className="hint">Nulla viene pubblicato finché non approvi: il testo generato dall'AI è sempre una bozza.</p>
      </div>

      <Preview platform={account?.platform} text={text} media={media} />
    </div>
  );
}
