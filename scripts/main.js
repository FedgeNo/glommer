import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { RelativeTime } from '/scripts/RelativeTime.js';
import { ScrollToTop } from '/scripts/ScrollToTop.js';
import { WebSocketManager } from '/scripts/WebSocketManager.js';
import { CarouselController } from '/scripts/CarouselController.js';
import { EmojiRenderer } from '/scripts/EmojiRenderer.js';
import { ClientConfig } from '/scripts/ClientConfig.js';
import '/scripts/dom.js';

ReadyHandler.add(RelativeTime.init);
ReadyHandler.add(ScrollToTop.init);
ReadyHandler.add(EmojiRenderer.init);

if (document.querySelector('.User, .UserList')) {
    import('/scripts/User.js');
    import('/scripts/OtherUser.js');
}
if (document.querySelector('.BannedUser'))             import('/scripts/BannedUser.js');
if (document.querySelector('.Post')) {
    import('/scripts/Post.js');
    import('/scripts/PostEditor.js');
}
if (document.querySelector('.MessageList'))            import('/scripts/Message.js');
if (document.querySelector('.NotificationList'))       import('/scripts/Notification.js');
if (document.querySelector('.ReportList'))             import('/scripts/ReportCard.js');
if (document.querySelector('.TrendingEntityChip'))     import('/scripts/TrendingEntity.js');

if (document.querySelector('.PostComposer, .ReplyComposer') && ClientConfig.get('currentUserId') !== null) {
    import('/scripts/Composer.js');
}

if (document.querySelector('[data-infinite-scroll]'))  import('/scripts/InfiniteScroller.js');
if (document.querySelector('.SearchInput'))            import('/scripts/Search.js');
if (document.querySelector('.MessageComposer'))        import('/scripts/MessageComposer.js');
if (document.querySelector('.PostBody'))               import('/scripts/MathRenderer.js');

if (document.querySelector('.LoginForm'))              import('/scripts/LoginForm.js');
if (document.querySelector('.LogoutForm'))             import('/scripts/LogoutForm.js');
if (document.querySelector('.SignupForm'))             import('/scripts/SignupForm.js');
if (document.querySelector('.ChangePasswordForm'))     import('/scripts/ChangePasswordForm.js');
if (document.querySelector('.ChangeEmailForm'))        import('/scripts/ChangeEmailForm.js');
if (document.querySelector('.DeleteAccountForm'))      import('/scripts/DeleteAccountForm.js');
if (document.querySelector('.TwoFactorSettingsForm'))  import('/scripts/TwoFactorSettingsForm.js');
if (document.querySelector('.TwoFactorForm'))          import('/scripts/TwoFactorForm.js');
if (document.querySelector('.ForgotPasswordForm'))     import('/scripts/ForgotPasswordForm.js');
if (document.querySelector('.ResetPasswordForm'))      import('/scripts/ResetPasswordForm.js');
if (document.querySelector('.BotProtectionSettingsForm')) import('/scripts/BotProtectionSettingsForm.js');
if (document.querySelector('.GoogleAuthSettingsForm')) import('/scripts/GoogleAuthSettingsForm.js');
if (document.querySelector('.RemoteFollowsForm'))      import('/scripts/RemoteFollowsForm.js');
if (document.querySelector('.MailSettingsForm'))       import('/scripts/MailSettingsForm.js');
if (document.querySelector('.SiteInfoSettingsForm'))   import('/scripts/SiteInfoSettingsForm.js');
if (document.querySelector('.AvatarUploadForm'))       import('/scripts/AvatarUploadForm.js');
if (document.querySelector('.FaviconSettingsForm'))    import('/scripts/FaviconSettingsForm.js');
if (document.querySelector('.ThemeSelect'))            import('/scripts/ThemeSelect.js');
if (document.querySelector('.SignupForm'))             import('/scripts/UsernameValidation.js');
if (document.querySelector('.HashtagGraphList'))       import('/scripts/HashtagGraphList.js');
if (document.querySelector('.HelpSearchInput'))        import('/scripts/HelpSearch.js');
if (document.querySelector('.NotificationTestPanel'))  import('/scripts/NotificationTestPanel.js');
if (document.querySelector('.VideoCallTestPanel'))     import('/scripts/VideoCallTestPanel.js');
if (document.querySelector('.RevokeSessionButton'))    import('/scripts/RememberedDevice.js');
if (document.querySelector('.LogoutEverywherePanel'))  import('/scripts/LogoutEverywherePanel.js');
if (document.querySelector('.PostShareButton'))        import('/scripts/PostShareButton.js');
if (document.querySelector('.PostMap'))                import('/scripts/PostMap.js');
if (document.querySelector('.NearbyLocateButton'))     import('/scripts/NearbyLocationPrompt.js');
if (document.querySelector('.MapSettingsForm'))        import('/scripts/MapSettingsForm.js');
if (document.querySelector('.OpenRouterSettingsForm')) import('/scripts/OpenRouterSettingsForm.js');

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

