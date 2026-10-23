import { expect, it } from 'vitest';
import { splitHeaderTokens } from './splitHeaderTokens';
it('splits only top-level delimiters respecting quotes, comments and symbol options', () => {
    expect(splitHeaderTokens('mx.example; spf=pass reason="a;b"; dkim=fail (a; (b;c))', ';')).toEqual(['mx.example', 'spf=pass reason="a;b"', 'dkim=fail (a; (b;c))']);
    expect(splitHeaderTokens('RULE[one;two]; other', ';')).toEqual(['RULE[one;two]', 'other']);
    expect(splitHeaderTokens('a="b\\";c"; next', ';')).toEqual(['a="b\\";c"', 'next']);
});
it.each(['a; b="unfinished', 'a; b=(unclosed', 'a; b=]invalid'])('retains malformed input instead of guessing: %s', value => {
    expect(splitHeaderTokens(value, ';')).toEqual([value]);
});
