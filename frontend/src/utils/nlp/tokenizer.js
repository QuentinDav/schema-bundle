/** Lightweight tokenizer with phrase capture (e.g., "greater than"). */

const PHRASES = [
  // connectors
  'where', 'with', 'when', 'whose', 'for', 'having', 'order by', 'sort by', 'group by', 'limit',
  // operators (multi-word first)
  'greater than or equal to', 'less than or equal to', 'not equal to',
  'greater than', 'more than', 'less than', 'at least', 'no more than',
  'starts with', 'ends with', 'equal to', 'like', 'contains',
  // patterns
  'of'
]
  .sort((a,b) => b.length - a.length); // longest first

/**
 * @param {string} input
 * @returns {{raw:string,tokens:string[]}}
 */
export function tokenize(input) {
  const raw = (input || '').trim();
  let s = ' ' + raw.toLowerCase() + ' ';

  // protect phrases with sentinel underscores
  for (const p of PHRASES) {
    const re = new RegExp(`\\b${escapeRe(p)}\\b`, 'g');
    s = s.replace(re, p.split(' ').join('_'));
  }

  // split on whitespace and punctuation but keep things like a.b and a_b
  const tokens = s
    .split(/\s+/)
    .map(t => t.replace(/[",]/g, ''))
    .filter(Boolean)
    .map(t => t.replace(/_/g, ' ')) // restore phrases as single token with spaces
    .map(t => t.trim());

  return { raw, tokens };
}

function escapeRe(s){return s.replace(/[.*+?^${}()|[\]\\]/g,'\\$&');}
