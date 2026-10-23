# 0019: Media ingested by a failed save outside a transaction aren't pruned

- Status: accepted
- Date: 2026-10-23

## Context

`prepare()` can ingest a file when the model is saved ([0013](0013-defer-prepare-until-saving.md)), and the save can still fail afterwards. Repositories save in a database transaction, so the media row rolls back with the save, and the mediatheque, which dispatches its pipeline after the commit, doesn't process it. Its file is an orphan that `mediatheque:prune` deletes.

A model saved outside a transaction (`$model->save()` in application code) keeps its media when the save fails, without a usage. [0013](0013-defer-prepare-until-saving.md) left open whether the mediatheque prunes media that were never used, through its usage graph. Its `mediatheque:prune` force-deletes trashed media and orphan files; it doesn't look at usages.

## Decision

- **This package prunes nothing.** A media left by a failed save outside a transaction stays.
- **Saving in a transaction is the way to avoid it:** a repository, or `DB::transaction()` around `$model->save()`. The JSON data docs say so.
- **Pruning media without a usage belongs to the mediatheque**, if it is ever wanted. It isn't a condition of this package's alphas.

## Consequences

- Alpha 1 ships without it.
- The case is narrow: a save that fails after `saving`, from code that saves a model with a file in its JSON data, outside a repository and a transaction.
- A media without a usage isn't always a leftover: an application can ingest media before using them. A pruning by usage would need a grace period or a mark, which is the mediatheque's to design.
