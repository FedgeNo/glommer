import { ReadyHandler } from '/ReadyHandler.js';
import { RelativeTime } from '/RelativeTime.js';
import { ScrollToTop } from '/ScrollToTop.js';
import { WebSocketManager } from '/WebSocketManager.js';
import { CarouselController } from '/CarouselController.js';
import { EmojiRenderer } from '/EmojiRenderer.js';
import { ClientConfig } from '/ClientConfig.js';
import '/dom.js';

ReadyHandler.add(RelativeTime.init);
ReadyHandler.add(ScrollToTop.init);
ReadyHandler.add(EmojiRenderer.init);

if (document.querySelector('.User, .UserList')) {
    import('/User.js');
    import('/OtherUser.js');
}
if (document.querySelector('.BannedUser'))             import('/BannedUser.js');
if (document.querySelector('.Post')) {
    import('/Post.js');
    import('/PostEditor.js');
}
if (document.querySelector('.MessageList'))            import('/Message.js');
if (document.querySelector('.NotificationList'))       import('/Notification.js');
if (document.querySelector('.ReportList'))             import('/ReportCard.js');
if (document.querySelector('.TrendingEntityChip'))     import('/TrendingEntity.js');

if (document.querySelector('.PostComposer, .ReplyComposer') && ClientConfig.get('currentUserId') !== null) {
    import('/Composer.js');
}

if (document.querySelector('[data-infinite-scroll]'))  import('/InfiniteScroller.js');
if (document.querySelector('.SearchInput'))            import('/Search.js');
if (document.querySelector('.MessageComposer'))        import('/MessageComposer.js');
if (document.querySelector('.PostBody'))               import('/math.js');

if (document.querySelector('.LoginForm'))              import('/LoginForm.js');
if (document.querySelector('.LogoutForm'))             import('/LogoutForm.js');
if (document.querySelector('.SignupForm'))             import('/SignupForm.js');
if (document.querySelector('.ChangePasswordForm'))     import('/ChangePasswordForm.js');
if (document.querySelector('.ChangeEmailForm'))        import('/ChangeEmailForm.js');
if (document.querySelector('.DeleteAccountForm'))      import('/DeleteAccountForm.js');
if (document.querySelector('.TwoFactorSettingsForm'))  import('/TwoFactorSettingsForm.js');
if (document.querySelector('.TwoFactorForm'))          import('/TwoFactorForm.js');
if (document.querySelector('.ForgotPasswordForm'))     import('/ForgotPasswordForm.js');
if (document.querySelector('.ResetPasswordForm'))      import('/ResetPasswordForm.js');
if (document.querySelector('.BotProtectionSettingsForm')) import('/BotProtectionSettingsForm.js');
if (document.querySelector('.GoogleAuthSettingsForm')) import('/GoogleAuthSettingsForm.js');
if (document.querySelector('.RemoteFollowsForm'))      import('/RemoteFollowsForm.js');
if (document.querySelector('.MailSettingsForm'))       import('/MailSettingsForm.js');
if (document.querySelector('.SiteInfoSettingsForm'))   import('/SiteInfoSettingsForm.js');
if (document.querySelector('.AvatarUploadForm'))       import('/AvatarUploadForm.js');
if (document.querySelector('.FaviconSettingsForm'))    import('/FaviconSettingsForm.js');
if (document.querySelector('.ThemeSelect'))            import('/ThemeSelect.js');
if (document.querySelector('.SignupForm'))             import('/UsernameValidation.js');
if (document.querySelector('.HashtagGraphList'))       import('/HashtagGraphList.js');
if (document.querySelector('.HelpSearchInput'))        import('/help.js');
if (document.querySelector('.NotificationTestPanel'))  import('/NotificationTestPanel.js');
if (document.querySelector('.RevokeSessionButton'))    import('/RememberedDevice.js');
if (document.querySelector('.LogoutEverywherePanel'))  import('/LogoutEverywherePanel.js');

document.addEventListener('error', function(event) {
    const img = event.target;
    if (img instanceof HTMLImageElement && img.dataset.fullSrc && img.src !== img.dataset.fullSrc) {
        img.src = img.dataset.fullSrc;
        img.removeAttribute('data-full-src');
    }
}, true);

const wsManager = new WebSocketManager();
wsManager.init();
const statusLine = document.querySelector('.WebSocketClientStatus');
if (statusLine) wsManager.showStatus(statusLine);

const carousel = new CarouselController();
carousel.init();

