#!/usr/bin/env python3
"""Сводка расхода токенов Claude Code по логам сессий этого проекта.

Читает ~/.claude/projects/<проект>/*.jsonl (локальные логи Claude Code),
учитывает каждый API-запрос один раз и печатает Markdown-таблицу.

    python3 scripts/token-usage.py > docs/tokens/usage.md
"""
import collections
import glob
import json
import os
import sys

FIELDS = ['input_tokens', 'cache_creation_input_tokens', 'cache_read_input_tokens', 'output_tokens']
LABELS = ['Вход', 'Запись в кеш', 'Чтение из кеша', 'Выход']

project = os.path.abspath(sys.argv[1] if len(sys.argv) > 1 else '.')
log_dir = os.path.expanduser('~/.claude/projects/' + project.replace('/', '-').replace('.', '-'))

rows, total = [], collections.Counter()
for path in sorted(glob.glob(os.path.join(log_dir, '*.jsonl'))):
    seen, usage, models, first, last = set(), collections.Counter(), set(), None, None
    for line in open(path, encoding='utf-8'):
        try:
            entry = json.loads(line)
        except ValueError:
            continue
        msg = entry.get('message')
        if not isinstance(msg, dict) or not msg.get('usage'):
            continue
        key = (msg.get('id'), entry.get('requestId'))
        if key in seen:
            continue
        seen.add(key)
        models.add(msg.get('model', '?'))
        ts = entry.get('timestamp') or ''
        first, last = min(first or ts, ts), max(last or ts, ts)
        for f in FIELDS:
            usage[f] += msg['usage'].get(f) or 0
    if seen:
        usage['requests'] = len(seen)
        total.update(usage)
        rows.append((os.path.basename(path)[:8], first[:16].replace('T', ' '), last[11:16], ', '.join(sorted(models)), usage))

fmt = lambda n: f'{n:,}'.replace(',', ' ')
print('| Сессия | Время (UTC) | Модель | Запросов | ' + ' | '.join(LABELS) + ' |')
print('|---|---|---|--:|' + '--:|' * len(FIELDS))
for sid, first, last, models, u in rows:
    print(f'| {sid} | {first}–{last} | {models} | {u["requests"]} | ' + ' | '.join(fmt(u[f]) for f in FIELDS) + ' |')
print(f'| **Итого** | | | **{total["requests"]}** | ' + ' | '.join(f'**{fmt(total[f])}**' for f in FIELDS) + ' |')
