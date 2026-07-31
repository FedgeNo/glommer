# Todo

Site-wide work. Fediverse items live in their own list - see
[FEDIVERSE-TODO.md](FEDIVERSE-TODO.md).

## End-to-end encryption for messages

Messages are currently stored as plain text in `Messages.body`. They are private
in the ordinary sense - only the two people in the conversation can read them
through the site, and the moderation path was narrowed so a report no longer
exposes them to anyone else - but the server operator can read the table, and a
database backup contains every message in the clear.

End-to-end encryption would change that: the server would hold ciphertext it
cannot read, and only the two participants' devices could open it.

Worth knowing before starting, because these are the decisions that shape it
rather than details to settle later:

- **Key material lives on the client.** That is the whole point, and it is also
  the hard part: a browser losing its key means the history is gone, and no
  operator can recover it. Recovery phrases, or a passphrase-wrapped key stored
  server-side, are the usual answers and both have real costs.
- **Multiple devices** each need the key, which means a device-to-device
  handover or a wrapped key the passphrase unlocks.
- **Moderation needs message franking to survive.** A report can still carry the
  plaintext, because the reporter's own device already decrypted it - but a
  client can submit any text it likes and call it a message, so a naive report
  proves nothing and a moderator acting on one is acting on an assertion.

  The fix is a known technique, usually called message franking or verifiable
  abuse reporting: at send time the server commits to the ciphertext it relayed
  (an HMAC under a server-held key) and hands that tag back. A report carries the
  plaintext, the ciphertext and the tag; the server re-checks that the tag was
  one it issued for exactly that ciphertext, and that the plaintext opens it.
  That makes a report unforgeable without the server ever being able to read a
  message it was not shown. Worth designing in from the start - retrofitting it
  means old messages can never be reported.
- **Search and notification previews** over message bodies stop being possible
  server-side.
- **Federated DMs cannot be end-to-end encrypted at all.** ActivityPub has no
  mechanism for it, so a federated conversation stays readable by both servers
  regardless of what is done locally. Local and federated messages would have
  genuinely different privacy properties, and the interface has to say so.

Until this exists, the site must not claim messages are end-to-end encrypted.
