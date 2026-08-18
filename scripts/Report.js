import { ClientConfig } from '/scripts/ClientConfig.js';
import { RelativeTime } from '/scripts/RelativeTime.js';
import { parse_server_date } from '/scripts/utils.js';
import { Api } from '/scripts/Api.js';
import { Dialog } from '/scripts/Dialog.js';
import { DOMUtils } from '/scripts/DOMUtils.js';
import { Post } from '/scripts/Post.js';
import { User } from '/scripts/User.js';
import { InfiniteScroller } from '/scripts/InfiniteScroller.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { Working } from '/scripts/Working.js';
import { Strings } from '/scripts/Strings.js';

/**
 * Client-side mirror of the PHP Report (src/classes/Report.php) - the
 * moderation card the admin reports page appends on scroll from the data
 * api/report-history.php returns. Left column: who reported what, the reported
 * content itself (a bare post, a message body, a user's profile card, or a
 * "no longer exists" notice), the reason, and when. Right column: the same ban
 * / delete / dismiss buttons the server renders, whose delegated handlers live
 * in main.js.
 */
export class Report {
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
        const card = new Report();
        Object.assign(card, data);
        return card;
    }

    /** Mirrors Report::targetContentElement - the reported item itself. */
    targetContentElement(words) {
        const target = this.target || { kind: 'missing' };

        if (target.kind === 'post' && target.post) {
            const post = Post.fromData(target.post).postElement();

            // A deleted post's reported media, streamed from the kept originals
            // via the mod-only passthrough (mediaType already resolved server-side).
            if (Array.isArray(target.attachments) && target.attachments.length > 0) {
                const media = document.createElement('div');
                media.className = 'ReportedAttachments';
                target.attachments.forEach((attachment) => media.appendWithSpace(forensic_attachment_element(attachment, words)));
                post.appendWithSpace(media);
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
            return User.fromData(target.user).toElement();
        }

        // missing / unknown - a muted notice (mirrors the PHP Notice element).
        // The server already resolved and localized target.message; the
        // fallback here is only for a payload that somehow carries neither.
        const notice = document.createElement('p');
        notice.className = 'Notice';
        notice.textContent = target.message || words.missing.unknownType;
        return notice;
    }

    toElement() {
        const words = Strings.for('Report', {
            targetTypes: { post: 'Post', message: 'Message', user: 'User' },
            summary: { before: '{type} #{id} reported by ', after: '' },
            reasonLine: 'Reason: {reason}',
            banReporterLabel: 'Ban Reporter',
            banReportedUserLabel: 'Ban Reported User',
            deleteLabel: 'Delete {type}',
            reportedImageAlt: 'Reported image',
            attachmentUnavailable: 'A reported attachment is no longer available.',
            viewAttachment: 'View reported attachment',
            missing: {
                noSnapshot: 'The reported content is no longer available.',
                unknownType: 'Unknown content type.',
            },
        });
        const type_label = words.targetTypes[this.targetType] || capitalize(this.targetType);

        const card = document.createElement('article');
        card.className = 'Report';

        const details = document.createElement('div');
        details.className = 'ReportDetails';

        const summary = document.createElement('div');
        summary.appendWithSpace(document.createTextNode(
            words.summary.before.replace('{type}', type_label).replace('{id}', this.targetId)
        ));

        const reporter_link = document.createElement('a');
        reporter_link.href = ClientConfig.siteURL() + '/users/' + this.reporterUsername + '/';
        reporter_link.textContent = this.reporterUsername;
        summary.appendWithSpace(reporter_link);
        summary.appendWithSpace(document.createTextNode(words.summary.after));
        details.appendWithSpace(summary);

        details.appendWithSpace(this.targetContentElement(words));

        if (this.reason !== null && this.reason !== undefined) {
            const reason_line = document.createElement('p');
            reason_line.textContent = words.reasonLine.replace('{reason}', this.reason);
            details.appendWithSpace(reason_line);
        }

        if (this.createdAt) {
            const meta = document.createElement('time');
        meta.className = 'RelativeTime ReportMeta';
            meta.dateTime = parse_server_date(this.createdAt).toISOString();
            meta.textContent = RelativeTime.format(this.createdAt);
            details.appendWithSpace(meta);
        }

        card.appendWithSpace(details);

        const actions = document.createElement('div');
        actions.className = 'ReportActions';

        // The admin (userId 1) can't be banned, so no Ban Reporter when the
        // admin filed the report. (The reported user is never the admin - the
        // report API rejects reports about admin content.)
        if (Number(this.reporterId) !== 1) {
            actions.appendWithSpace(this.banButton(this.reporterId, words.banReporterLabel));
        }

        if (this.targetUserId !== null && this.targetUserId !== undefined
            && this.targetUsername !== null && this.targetUsername !== undefined
            && Number(this.targetUserId) !== Number(this.reporterId)) {
            actions.appendWithSpace(this.banButton(this.targetUserId, words.banReportedUserLabel));
        }

        // Only offer Delete when the live post/message still exists (a snapshot
        // of already-deleted content still shows, but has nothing to delete).
        if (this.targetLive && (this.targetType === 'post' || this.targetType === 'message')) {
            const delete_button = document.createElement('button');
            delete_button.type = 'button';
            delete_button.className = 'Button ReportedContentDeleteButton';
            delete_button.dataset.reportId = this.reportId;
            delete_button.textContent = words.deleteLabel.replace('{type}', type_label);
            actions.appendWithSpace(delete_button);
        }

        // Only a post has media to put behind a cover.
        if (this.targetLive && this.targetType === 'post') {
            const classify_button = document.createElement('button');
            classify_button.type = 'button';
            classify_button.className = 'Button ReportedContentClassifyButton';
            classify_button.dataset.reportId = this.reportId;
            classify_button.textContent = Strings.for('ReportedContentClassifyButton', { name: 'Mark Sensitive' }).name;
            actions.appendWithSpace(classify_button);
        }

        const dismiss_button = document.createElement('button');
        dismiss_button.type = 'button';
        dismiss_button.className = 'Button ReportDismissButton';
        dismiss_button.dataset.reportId = this.reportId;
        dismiss_button.textContent = Strings.for('ReportDismissButton', { name: 'Dismiss' }).name;
        actions.appendWithSpace(dismiss_button);

        card.appendWithSpace(actions);

        this.element = card;

        return card;
    }

    banButton(user_id, label) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'Button UserBanButton';
        button.dataset.userId = user_id;
        button.textContent = label;

        return button;
    }

    // ----------------------------------------------------------------
    // Static action handlers (dismiss, delete reported content)
    // ----------------------------------------------------------------

    static init() {
        document.addEventListener('click', async (event) => {
            const dismissBtn = event.target.closest('.ReportDismissButton');
            if (dismissBtn) {
                Report.#dismiss(dismissBtn);
                return;
            }

            const deleteBtn = event.target.closest('.ReportedContentDeleteButton');
            if (deleteBtn) {
                Report.#deleteContent(deleteBtn);
                return;
            }

            const classifyBtn = event.target.closest('.ReportedContentClassifyButton');
            if (classifyBtn) {
                Report.#classifyContent(classifyBtn);
            }
        });
    }

    static async #classifyContent(button) {
        Working.start(button);
        try {
            const result = await Api.post('/api/classify-reported-content', { reportId: button.dataset.reportId });
            if (!result) return;
            DOMUtils.slideOut(button.closest('.Report'));
        } finally {
            Working.stop(button);
        }
    }

    static async #dismiss(button) {
        Working.start(button);
        try {
            const result = await Api.post('/api/dismiss-report', { reportId: button.dataset.reportId });
            if (!result) return;
            DOMUtils.slideOut(button.closest('.Report'));
        } finally {
            Working.stop(button);
        }
    }

    static async #deleteContent(button) {
        if (!await Dialog.confirm('Delete this content permanently? Deleting a post also removes all its replies.')) return;
        Working.start(button);
        try {
            const result = await Api.post('/api/delete-reported-content', { reportId: button.dataset.reportId });
            if (!result) return;
            DOMUtils.slideOut(button.closest('.Report'));
        } finally {
            Working.stop(button);
        }
    }
}

/** Uppercases the first character - the JS side of PHP's ucfirst(). */
function capitalize(text) {
    const value = text || '';
    return value.charAt(0).toUpperCase() + value.slice(1);
}

/**
 * One reported attachment of a deleted post (mirrors Report::forensicAttachmentElement):
 * an img/video/audio pointed at the mod-only passthrough, a notice when the
 * original is gone, or a link for any other type. mediaType is resolved server-side.
 */
function forensic_attachment_element(attachment, words) {
    if (attachment.mediaType === 'image') {
        const image = document.createElement('img');
        image.className = 'ReportedMedia';
        image.src = attachment.url;
        image.alt = words.reportedImageAlt;
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
        notice.className = 'Notice';
        notice.textContent = words.attachmentUnavailable;
        return notice;
    }

    const link = document.createElement('a');
    link.href = attachment.url;
    link.target = '_blank';
    link.rel = 'noopener';
    link.textContent = words.viewAttachment;
    return link;
}

ReadyHandler.add(Report.init);
