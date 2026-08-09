import { ReadyHandler } from '/scripts/ReadyHandler.js';
import { RelativeTime } from '/scripts/RelativeTime.js';
import { ScrollToTop } from '/scripts/ScrollToTop.js';
import { WebSocketManager } from '/scripts/WebSocketManager.js';
import { CarouselController } from '/scripts/CarouselController.js';
import { EmojiRenderer } from '/scripts/EmojiRenderer.js';
import { ClientConfig } from '/scripts/ClientConfig.js';
import { sync_theme_color } from '/scripts/utils.js';
import '/scripts/dom.js';

ReadyHandler.add(RelativeTime.init);
ReadyHandler.add(ScrollToTop.init);
ReadyHandler.add(EmojiRenderer.init);
ReadyHandler.add(sync_theme_color);

// The navigation is on every page that has any, and this only adds a spoken
// state to a menu that already works without it.
if (document.getElementById('NavToggle')) import('/scripts/NavMenu.js');

if (document.querySelector('.User, .UserList')) {
    import('/scripts/User.js');
    import('/scripts/OtherUser.js');
}
if (document.querySelector('.BannedUser'))             import('/scripts/BannedUser.js');
if (document.querySelector('.Post')) {
    import('/scripts/Post.js');
    import('/scripts/PostEditor.js');
}
if (document.querySelector('.Poll'))                   import('/scripts/Poll.js');
if (document.querySelector('.SensitiveMediaSetting'))  import('/scripts/SensitiveMediaSetting.js');
if (document.querySelector('.EmailDigestSetting'))     import('/scripts/EmailDigestSetting.js');
// Keyed on the composer, not the list: a thread nobody has written in yet
// renders no list, only the notice saying so.
if (document.querySelector('.MessageComposer'))        import('/scripts/Message.js');
if (document.querySelector('.MessageComposer[data-other-user-id]')) import('/scripts/VideoCall.js');
if (document.querySelector('.MessageUnlockForm'))      import('/scripts/MessageUnlockForm.js');
if (document.querySelector('.MessageKeyFingerprint'))  import('/scripts/MessageKeyFingerprint.js');
if (document.querySelector('.EncryptedMessagesSetting')) import('/scripts/EncryptedMessagesSetting.js');
// .MessageComposer too: a thread with nothing in it has no report button yet,
// but the first message to arrive live brings one.
if (document.querySelector('.ReportButton, .MessageComposer')) import('/scripts/ReportButton.js');
if (document.querySelector('.NotificationList, .NotificationDropdown')) import('/scripts/Notification.js');
if (document.querySelector('.ReportList'))             import('/scripts/ReportCard.js');
if (document.querySelector('.TrendingEntityChip'))     import('/scripts/TrendingEntity.js');
if (document.querySelector('.BlockedServersSetting'))  import('/scripts/BlockedServerCard.js');
if (document.querySelector('.RelaysSetting'))          import('/scripts/RelayCard.js');
if (document.querySelector('.StagedPostCard'))         import('/scripts/StagedPostCard.js');
if (document.querySelector('.WelcomeBanner'))          import('/scripts/WelcomeBanner.js');

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
if (document.querySelector('.PasswordChangeForm'))     import('/scripts/PasswordChangeForm.js');
if (document.querySelector('.EmailChangeForm'))        import('/scripts/EmailChangeForm.js');
if (document.querySelector('.AccountDeleteForm'))      import('/scripts/AccountDeleteForm.js');
if (document.querySelector('.TwoFactorSettingsForm'))  import('/scripts/TwoFactorSettingsForm.js');
if (document.querySelector('.TwoFactorForm'))          import('/scripts/TwoFactorForm.js');
if (document.querySelector('.PasswordResetRequestForm')) import('/scripts/PasswordResetRequestForm.js');
if (document.querySelector('.PasswordResetForm'))      import('/scripts/PasswordResetForm.js');
if (document.querySelector('.BotProtectionSettingsForm')) import('/scripts/BotProtectionSettingsForm.js');
if (document.querySelector('.GoogleAuthSettingsForm')) import('/scripts/GoogleAuthSettingsForm.js');
if (document.querySelector('.RemoteFollowsForm'))      import('/scripts/RemoteFollowsForm.js');
if (document.querySelector('.AccountMigrationForm'))    import('/scripts/AccountMigrationForm.js');
if (document.querySelector('.MailSettingsForm'))       import('/scripts/MailSettingsForm.js');
if (document.querySelector('.EmailDigestSettingsForm')) import('/scripts/EmailDigestSettingsForm.js');
if (document.querySelector('.SiteInfoSettingsForm'))   import('/scripts/SiteInfoSettingsForm.js');
if (document.querySelector('.AvatarUploadForm'))       import('/scripts/AvatarUploadForm.js');
if (document.querySelector('.FaviconSettingsForm'))    import('/scripts/FaviconSettingsForm.js');
if (document.querySelector('.FrontPageImageSettingsForm')) import('/scripts/FrontPageImageSettingsForm.js');
if (document.querySelector('.ThemeSelect'))            import('/scripts/ThemeSelect.js');
if (document.querySelector('.SignupForm'))             import('/scripts/UsernameValidation.js');
if (document.querySelector('.HashtagGraphList'))       import('/scripts/HashtagGraphList.js');
if (document.querySelector('.HelpSearchInput'))        import('/scripts/HelpSearch.js');
if (document.querySelector('.NotificationTestPanel'))  import('/scripts/NotificationTestPanel.js');
if (document.querySelector('.VideoCallTestPanel'))     import('/scripts/VideoCallTestPanel.js');
if (document.querySelector('.RememberedDeviceRevokeButton')) import('/scripts/RememberedDevice.js');
if (document.querySelector('.LogoutEverywherePanel'))  import('/scripts/LogoutEverywherePanel.js');
if (document.querySelector('.PostShareButton'))        import('/scripts/PostShareButton.js');
if (document.querySelector('.PostPinButton'))          import('/scripts/PostPinButton.js');
if (document.querySelector('.PostRepostButton'))       import('/scripts/PostRepostButton.js');
if (document.querySelector('.PostMap'))                import('/scripts/PostMap.js');
if (document.querySelector('.NearbyLocationButton'))   import('/scripts/NearbyLocationPrompt.js');
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

// Register the service worker so push (and installability) can work at all -
// only for a signed-in visitor, since there is nothing to be notified about
// otherwise, and only where the browser supports it.
if ('serviceWorker' in navigator && ClientConfig.get('currentUserId') !== null) {
    navigator.serviceWorker.register(ClientConfig.siteURL() + '/sw.js').catch(() => {});
}

if (document.querySelector('.PushSubscribeButton')) import('/scripts/PushNotificationSetting.js');

