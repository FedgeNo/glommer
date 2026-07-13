/**
 * Client-side mirror of the PHP ReportCard (src/Classes/ReportCard.php) - the
 * moderation card the admin reports page appends on scroll from the data
 * api/report-history.php returns. Left column: who reported what, the reported
 * content itself (a bare post, a message body, a user's profile card, or a
 * "no longer exists" notice), the reason, and when. Right column: the same ban
 * / delete / dismiss buttons the server renders, whose delegated handlers live
 * in main.js.
 */
class ReportCard {
    reportId = null;
    reporterId = null;
    reporterUsername = null;
    targetType = null;
    targetId = null;
    reason = null;
    createdAt = null;
    targetUserId = null;
    targetUsername = null;
    targetLive = false;
    target = null;
    element = null;

    static fromData(data) {
        const card = new ReportCard();
        Object.assign(card, data);
        return card;
    }

    /** Mirrors ReportCard::targetContentElement - the reported item itself. */
    targetContentElement() {
        const target = this.target || { kind: 'missing' };

        if (target.kind === 'post' && target.post) {
            const post = Post.fromData(target.post).postElement();

            // A deleted post's reported media, streamed from the kept originals
            // via the mod-only passthrough (mediaType already resolved server-side).
            if (Array.isArray(target.attachments) && target.attachments.length > 0) {
                const media = document.createElement('div');
                media.className = 'ReportedAttachments d-flex flex-column gap-2';
                target.attachments.forEach((attachment) => media.appendChild(forensic_attachment_element(attachment)));
                post.appendChild(media);
            }

            return post;
        }

        if (target.kind === 'message') {
            const quote = document.createElement('blockquote');
            quote.className = 'ReportedContent';
            quote.textContent = target.body || '';
            return quote;
        }

        if (target.kind === 'user' && target.user) {
            return user_card_element(target.user);
        }

        // missing / unknown - a muted notice (mirrors the PHP Notice element).
        const notice = document.createElement('p');
        notice.className = 'Muted Notice';
        notice.textContent = target.message || 'Unknown content type.';
        return notice;
    }

    toElement() {
        const card = document.createElement('div');
        card.className = 'Card ReportCard d-flex gap-3 align-items-start';

        const details = document.createElement('div');
        details.className = 'ReportDetails d-flex flex-column gap-2';

        const summary = document.createElement('div');
        summary.appendChild(document.createTextNode(capitalize(this.targetType) + ' #' + this.targetId + ' reported by '));

        const reporter_link = document.createElement('a');
        reporter_link.href = window.siteURL + '/users/' + this.reporterUsername + '/';
        reporter_link.textContent = this.reporterUsername;
        summary.appendChild(reporter_link);
        details.appendChild(summary);

        details.appendChild(this.targetContentElement());

        if (this.reason !== null && this.reason !== undefined) {
            const reason_line = document.createElement('p');
            reason_line.textContent = 'Reason: ' + this.reason;
            details.appendChild(reason_line);
        }

        if (this.createdAt) {
            const meta = document.createElement('time');
            meta.className = 'Muted text-sm RelativeTime';
            meta.dateTime = parse_server_date(this.createdAt).toISOString();
            meta.textContent = format_relative_time(this.createdAt);
            details.appendChild(meta);
        }

        card.appendChild(details);

        const actions = document.createElement('div');
        actions.className = 'ReportActions d-flex flex-column gap-2 ms-auto';

        // The admin (userId 1) can't be banned, so no Ban Reporter when the
        // admin filed the report. (The reported user is never the admin - the
        // report API rejects reports about admin content.)
        if (Number(this.reporterId) !== 1) {
            actions.appendChild(this.banButton(this.reporterId, 'Ban Reporter'));
        }

        if (this.targetUserId !== null && this.targetUserId !== undefined
            && this.targetUsername !== null && this.targetUsername !== undefined
            && Number(this.targetUserId) !== Number(this.reporterId)) {
            actions.appendChild(this.banButton(this.targetUserId, 'Ban Reported User'));
        }

        // Only offer Delete when the live post/message still exists (a snapshot
        // of already-deleted content still shows, but has nothing to delete).
        if (this.targetLive && (this.targetType === 'post' || this.targetType === 'message')) {
            const delete_button = document.createElement('button');
            delete_button.type = 'button';
            delete_button.className = 'Btn DeleteReportedContentButton';
            delete_button.dataset.reportId = this.reportId;
            delete_button.textContent = 'Delete ' + capitalize(this.targetType);
            actions.appendChild(delete_button);
        }

        const dismiss_button = document.createElement('button');
        dismiss_button.type = 'button';
        dismiss_button.className = 'Btn DismissReportButton';
        dismiss_button.dataset.reportId = this.reportId;
        dismiss_button.textContent = 'Dismiss';
        actions.appendChild(dismiss_button);

        card.appendChild(actions);

        this.element = card;

        return card;
    }

    banButton(user_id, label) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'Btn BanButton';
        button.dataset.userId = user_id;
        button.textContent = label;

        return button;
    }
}

/** Uppercases the first character - the JS side of PHP's ucfirst(). */
function capitalize(text) {
    const value = text || '';

    return value.charAt(0).toUpperCase() + value.slice(1);
}

/**
 * One reported attachment of a deleted post (mirrors ReportCard::forensicAttachmentElement):
 * an img/video/audio pointed at the mod-only passthrough, a notice when the
 * original is gone, or a link for any other type. mediaType is resolved server-side.
 */
function forensic_attachment_element(attachment) {
    if (attachment.mediaType === 'image') {
        const image = document.createElement('img');
        image.className = 'ReportedMedia';
        image.src = attachment.url;
        image.alt = 'Reported image';
        return image;
    }

    if (attachment.mediaType === 'video') {
        const video = document.createElement('video');
        video.className = 'ReportedMedia';
        video.controls = true;
        video.src = attachment.url;
        return video;
    }

    if (attachment.mediaType === 'audio') {
        const audio = document.createElement('audio');
        audio.controls = true;
        audio.src = attachment.url;
        return audio;
    }

    if (attachment.mediaType === null || attachment.mediaType === undefined) {
        const notice = document.createElement('p');
        notice.className = 'Muted Notice';
        notice.textContent = 'A reported attachment is no longer available.';
        return notice;
    }

    const link = document.createElement('a');
    link.href = attachment.url;
    link.target = '_blank';
    link.rel = 'noopener';
    link.textContent = 'View reported attachment';
    return link;
}
