<?php

namespace App\Observers;

use App\Helpers\PaymentHelper;
use App\Model\ReferralCodeUsage;
use App\Providers\AuthServiceProvider;
use App\Providers\GenericHelperServiceProvider;
use App\Providers\ListsHelperServiceProvider;
use App\User;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;

class UsersObserver
{
    /**
     * Listen to the User deleting event.
     *
     * @param User $user
     * @return void
     */
    public function deleting(User $user)
    {
        $paymentHelper = new PaymentHelper();
        foreach ($user->activeSubscriptions()->get() as $subscription) {
            try {
                $cancelSubscription = $paymentHelper->cancelSubscription($subscription);
                if (!$cancelSubscription) {
                    Log::error("Failed cancelling subscription for id: " . $subscription->id);
                }
            } catch (\Exception $exception) {
                Log::error("Failed cancelling subscription for id: " . $subscription->id . " error: " . $exception->getMessage());
            }
        }
    }

    /**
     * Listen to the User created event.
     *
     * @param User $user
     * @return void
     */
    public function created(User $user) {
        if ($user != null) {
            GenericHelperServiceProvider::createUserWallet($user);
            ListsHelperServiceProvider::createUserDefaultLists($user->id);
            if(getSetting('security.default_2fa_on_register')) {
                AuthServiceProvider::addNewUserDevice($user->id, true);
            }
            if(getSetting('profiles.default_users_to_follow')){
                $usersToFollow = explode(',',getSetting('profiles.default_users_to_follow'));
                if(count($usersToFollow)){
                    foreach($usersToFollow as $userID){
                        ListsHelperServiceProvider::managePredefinedUserMemberList($user->id,$userID,'follow');
                    }
                }
            }
            if(getSetting('referrals.enabled')) {
                // Saving the referral even if the case
                if(Cookie::has('referral')){
                    $referralID = User::where('referral_code', Cookie::get('referral'))->first();
                    if($referralID){
                        $existing = ReferralCodeUsage::where(['used_by' => $user->id, 'referral_code' => $referralID->referral_code])->first();
                        if(!$existing) {
                            ReferralCodeUsage::create(['used_by' => $user->id, 'referral_code' => $referralID->referral_code]);
                            Cookie::queue(Cookie::forget('referral'));
                            if(getSetting('referrals.auto_follow_the_user')){
                                ListsHelperServiceProvider::managePredefinedUserMemberList($user->id,$referralID->id,'follow');
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Listen to the User updating event.
     *
     * @param User $user
     * @return void
     */
    public function updating(User $user) {
        // fixes the problem with admin panel saving invalid paths for user avatar and cover
        if($user->isDirty('avatar') && $user->getOriginal('avatar')) {
            // make sure we don't use the same files
            if(basename($user->avatar) === basename($user->getOriginal('avatar'))) {
                unset($user->avatar);
            }
        }
        if($user->isDirty('cover') && $user->getOriginal('cover')) {
            if(basename($user->cover) === basename($user->getOriginal('cover'))) {
                unset($user->cover);
            }
        }
    }
}
