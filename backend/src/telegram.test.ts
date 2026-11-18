import { describe, expect, it } from 'vitest';
import { attachmentLabel, botLang, chatGone, hourLabel, onOff, parseCommand, parseHour, tt, webhookSecret } from './telegram.js';

describe('telegram bot (D-75)', () => {
  it('parses commands, aliases and the @bot suffix', () => {
    expect(parseCommand('/boletim on Antica', 'bagparser_bot')).toEqual({ cmd: 'boletim', args: ['on', 'Antica'], rest: 'on Antica' });
    expect(parseCommand('/bulletin@BagParser_Bot', 'bagparser_bot')).toEqual({ cmd: 'boletim', args: [], rest: '' });
    expect(parseCommand('/start abc_DEF-123', 'bagparser_bot')?.args).toEqual(['abc_DEF-123']);
    expect(parseCommand('/suporte meu loot\nsumiu', 'bagparser_bot')?.rest).toBe('meu loot\nsumiu');
    expect(parseCommand('/boletim@other_bot', 'bagparser_bot')).toBeNull();
    expect(parseCommand('/xyz', 'bagparser_bot')?.cmd).toBe('?');
    expect(parseCommand('oi, preciso de ajuda', 'bagparser_bot')).toBeNull();
    expect(parseCommand(undefined, null)).toBeNull();
  });

  it('on/off, hours and languages', () => {
    expect(onOff('ligar')).toBe(true);
    expect(onOff('OFF')).toBe(false);
    expect(onOff('Antica')).toBeNull();
    expect(parseHour('8')).toBe(8);
    expect(parseHour('08:00')).toBe(8);
    expect(parseHour('21h')).toBe(21);
    expect(parseHour('24')).toBeNull();
    expect(parseHour('oito')).toBeNull();
    expect(botLang('pt-br')).toBe('pt');
    expect(botLang('pl')).toBe('en');
    expect(hourLabel(8, 'America/Sao_Paulo', 'pt')).toBe('8h (Brasília)');
    expect(hourLabel(8, 'Europe/Warsaw', 'en')).toBe('08:00 (Europe/Warsaw)');
  });

  it('texts with placeholders in both languages', () => {
    expect(tt('pt', 'support_sent', { id: 12 })).toContain('Ticket #12');
    expect(tt('en-US', 'support_sent', { id: 12 })).toContain('Ticket #12');
    expect(tt('pt', 'world_set', { world: 'Antica' })).toContain('<b>Antica</b>');
  });

  it('webhook secret: derived and stable, explicit one only when valid', () => {
    const tok = '123456789:AAAbbbCCCdddEEEfffGGGhhhIIIjjjKKKlll';
    expect(webhookSecret(tok)).toMatch(/^[a-f0-9]{48}$/);
    expect(webhookSecret(tok)).toBe(webhookSecret(tok));
    expect(webhookSecret(tok, 'my_secret_value_123')).toBe('my_secret_value_123');
    expect(webhookSecret(tok, 'bad secret!')).toBe(webhookSecret(tok));
  });

  it('attachments and gone chats', () => {
    expect(attachmentLabel({ message_id: 1, chat: { id: 1, type: 'private' }, photo: [] }, 'pt')).toBe('[imagem]');
    expect(attachmentLabel({ message_id: 1, chat: { id: 1, type: 'private' }, document: { file_name: 'log.txt' } }, 'en')).toBe('[file: log.txt]');
    expect(attachmentLabel({ message_id: 1, chat: { id: 1, type: 'private' }, text: 'oi' }, 'pt')).toBeNull();
    expect(chatGone({ ok: false, error_code: 403, description: 'Forbidden: bot was blocked by the user' })).toBe(true);
    expect(chatGone({ ok: false, error_code: 400, description: 'Bad Request: chat not found' })).toBe(true);
    expect(chatGone({ ok: false, error_code: 429, description: 'Too Many Requests' })).toBe(false);
  });
});
