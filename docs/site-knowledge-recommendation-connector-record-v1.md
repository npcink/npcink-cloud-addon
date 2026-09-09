# Site Knowledge Recommendation Connector Record v1

Status: active connector record.

This document records the WordPress-side lessons for article-index coverage
and Cloud-backed related-article recommendations. Cloud owns vector/index
truth; WordPress owns content display and editor actions.

## Connector Rules

- Send only public published posts/pages through the existing signed runtime
  transport.
- Reuse one local article snapshot for both the request manifest and display
  rows so titles, URLs, and statuses cannot drift between renders.
- Require `indexed_post_ids_requested` to equal the local manifest count before
  showing article-level `not_indexed` rows.
- Keep missing evidence fail-closed and explain it as unavailable/incomplete,
  not as an empty index.
- Keep manual refresh and single-article refresh capability/nonce protected.
- Do not expose embedding provider, collection, rerank, or vector database
  controls in the Addon page.

## UI And Debugging Lessons

The article coverage action must bind even when the page has no usage-quota
nodes. Initialization should be gated on the coverage surface itself, not only
on unrelated detail elements.

The default `Not indexed` view is intentionally action-oriented. When the
count is zero, its empty state is correct; operators can choose `All` or
`Indexed` to inspect the confirmed comparison. A successful summary with an
empty default list is not by itself a rendering bug.

The coverage surface is accepted only when the browser confirms:

- the summary count equals the confirmed comparison count;
- `All`, `Not indexed`, and `Indexed` filters preserve state;
- 50-row pagination works;
- no horizontal overflow or console/network failure is introduced;
- technical delivery details stay collapsed by default;
- the page remains fully localized.

## Troubleshooting Order

1. Confirm Addon credentials and Site Knowledge delivery consent.
2. Inspect the local public manifest count.
3. Send a small status request, then the full bounded manifest.
4. If the small request succeeds and the full request fails, inspect the outer
   Cloud runtime shape limit before investigating embeddings.
5. Confirm the Cloud requested-count evidence.
6. Only then inspect Cloud index membership or article display projection.

See [Site Knowledge Vector Operations](site-knowledge-vector-operations.md)
for the active connector contract and ownership rules.
