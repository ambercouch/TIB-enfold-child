# Document description ACF key migration

This change pairs the Document Fields PHP key
`field_document_short_description` with five existing `_short_description`
references. The field name remains `short_description`; no description value,
field setting, therapist/additional-service reference, orphan row or importer
payload is rewritten. Nothing runs automatically on frontend/admin requests.

## Scope and prerequisites

The migration deliberately recognises only:

| ID | Required post type | Required parent |
| --- | --- | --- |
| 8245 | documents | 0 |
| 8246 | documents | 0 |
| 8470 | documents | 0 |
| 8474 | documents | 0 |
| 10429 | revision | 8474 |

It requires InnoDB posts/postmeta tables. Each target must have exactly one
`_short_description` row with either the old or new key. Other owners, missing
records, duplicate references and unexpected values cause an error before any
metadata update. IDs are not automatically guessed or remapped for other sites.

## Staging and production rollout

1. Obtain the required environment/deployment approval. Take the normal database
   and theme backup. Confirm these IDs still have the ownership above and check
   for additional document references to the old key. If IDs differ or more
   records require migration, stop and review a separately authorised mapping;
   this script never broadens its scope automatically.
2. Pause document/profile editing during the paired code/data change. Deploy the
   one-key `functions.php` change and this script together. Do not leave an
   extended interval with the new field definition and old document references.
3. From the WordPress docroot, run the read-only preview:

   ```sh
   wp eval-file wp-content/themes/enfold-child/tools/migrate-document-description-key.php dry-run
   ```

4. Choose an existing secure backup directory outside **all served web roots**,
   including any aliases. Then apply (substitute the actual absolute path):

   ```sh
   wp eval-file wp-content/themes/enfold-child/tools/migrate-document-description-key.php apply /secure/backups/document-description-key-before.json
   ```

   The script refuses an existing backup filename, verifies the new ACF field
   definition, writes/verifies a mode-0600 JSON backup of target ownership and
   both description/reference rows, then locks/rechecks the snapshot and updates
   only exact old-key matches inside a transaction. Ownership and old values are
   checked for every update. A failed check rolls the updates back. Post-meta and
   ACF value caches for the five targets are invalidated after success.
5. Rerun the same apply command. It must report `Already migrated` with no writes
   or new backup. Mixed old/new states are supported; only remaining old rows
   change, with a fresh backup path required when work remains.
6. Verify `acf_get_field('field_document_short_description')` belongs to
   `group_document_fields`, while `acf_get_field('field_short_description')`
   belongs to `group_therapist_details`. Check all five stored references, the
   four document descriptions and representative therapist/profile output.
   Confirm Staff Resources, Policies and grouped Documents cards, plus empty
   Contracts. Run PHP lint. Resume editing after verification.

Production is a separate authorised deployment; running locally does not
apply anything to staging or production. Keep backup files out of Git/deploy
packages and restrict filesystem access. The docroot check cannot discover
additional hosting aliases, so the operator must confirm the destination.

## Repeatability and rollback

An already-migrated row is accepted without rewriting it. All five already
migrated is a no-op, including no backup write. Do not delete or alter unrelated
metadata to make preflight pass.

Before rollback, pause editing and verify the backup against current ownership,
meta IDs and new-key values. Restore only those five references to their saved
values, paired with restoring the previous Document Fields PHP key. Do not
restore a whole old metadata table or overwrite description values edited since
the backup. Use a separately reviewed rollback operation; this script has no
implicit reverse/global-replace mode. Invalidate target post-meta/ACF caches
and revalidate both definitions afterwards.

## Local execution

Applied locally to the five IDs above; the repeat apply was a no-op. Target
backup (outside `web`):
`backups/acf-document-key-20261020/metadata-before.json`.
A broader read-only metadata/importer snapshot was also captured under
`/tmp/tib-acf-document-key-backup/` for byte-for-byte preservation checks.
