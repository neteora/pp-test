import type { Media, Platform } from '../types';

interface Props {
  platform?: Platform;
  text: string;
  media: Media | null;
}

// Anteprima semplice di come apparirà il post sulla piattaforma scelta
export default function Preview({ platform, text, media }: Props) {
  return (
    <div className={`preview ${platform ?? ''}`}>
      <div className="preview-head">{platform ?? 'social'}</div>
      {media?.type.startsWith('image/') && <img src={media.publicUrl} alt="" />}
      {media?.type === 'video/mp4' && <video src={media.publicUrl} controls />}
      <p>{text || <em>Il testo apparirà qui</em>}</p>
    </div>
  );
}
