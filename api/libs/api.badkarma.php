<?php

/**
 * Basic MySQL user cash storage double precision fixing class
 */
class BadKarma {

    /**
     * Contains default online users detection path
     *
     * @var string
     */
    protected $onlineDataPath = '/etc/stargazer/dn/';

    /**
     * Contains all available users data as login=>userdata
     *
     * @var array
     */
    protected $allUsersData = array();

    /**
     * System message helper placeholder
     *
     * @var object
     */
    protected $messages = '';

    /**
     * Contains all of online users as login=>login
     *
     * @var array
     */
    protected $allOnlineUsers = array();

    /**
     * Contains default lower active user cash limit to detect his karma
     *
     * @var float
     */
    protected $lowerCashLimit = -0.02;

    /**
     * Default timeout between checks is actions really do something or not in sec.
     *
     * @var int
     */
    protected $waitTimeout = 1;

    /**
     * Some predefined routes etc..
     */
    const URL_PROFILE = '?module=userprofile&username=';
    const URL_ME = '?module=badkarma';
    const ROUTE_FIX = 'fixuserkarma';

    /**
     * Creates new BadKarma instance
     * 
     * @param bool $noDataLoad dont load full data set
     * 
     * @return void
     */
    public function __construct($noDataLoad = false) {
        $this->initMessages();
        if (!$noDataLoad) {
            $this->loadUserData();
            $this->loadOnlineUsers();
        }
    }

    /**
     * Inits system messages helper
     * 
     * @return void
     */
    protected function initMessages() {
        $this->messages = new UbillingMessageHelper();
    }

    /**
     * Loads existing userdata from database
     * 
     * @return void
     */
    protected function loadUserData() {
        $this->allUsersData = zb_UserGetAllData();
    }

    /**
     * Loads list of online users
     * 
     * @return void
     */
    protected function loadOnlineUsers() {
        if (file_exists($this->onlineDataPath)) {
            $all = rcms_scandir($this->onlineDataPath);
            if (!empty($all)) {
                foreach ($all as $io => $each) {
                    $this->allOnlineUsers[$each] = $each;
                }
            }
        }
    }

    /**
     * Checks is user acceptible to be online. Just with normal amount of money, not disabled, frozen, etc..
     * 
     * @param array $userData
     * 
     * @return bool
     */
    protected function userMustBeOnline($userData) {
        $result = false;

        //basic online availability flag
        if ($userData['AlwaysOnline'] == '1') {
            //user isnt disabled
            if ($userData['Down'] == '0') {
                //user is not frozen
                if ($userData['Passive'] == '0') {
                    //check financial amounts
                    if ($userData['Cash'] >= $this->lowerCashLimit) {
                        $result = true; // eezee, yeah?
                    }
                }
            }
        }
        return($result);
    }

    /**
     * Checks is user really online based on On* scripts actions
     * 
     * @param string $userLogin
     * 
     * @return bool
     */
    protected function userIsOnline($userLogin) {
        $result = false;
        if (!empty($userLogin)) {
            if (isset($this->allOnlineUsers[$userLogin])) {
                $result = true;
            }
        }
        return($result);
    }

    /**
     * Checks for user real online appear without refresh internal structs
     * 
     * @param string $userLogin
     * 
     * @return bool
     */
    protected function isUserOnlineRightNow($userLogin) {
        $result = false;
        if (file_exists($this->onlineDataPath . $userLogin)) {
            $result = true;
        }
        return($result);
    }

    /**
     * Trying to repair user online state with all of possible actions
     * 
     * @global object $billing
     * 
     * @param string $userLogin
     * 
     * @return void/string on error
     */
    public function fixUserCarma($userLogin) {
        global $billing;
        $result = '';
        $userData = (isset($this->allUsersData[$userLogin])) ? $this->allUsersData[$userLogin] : array();
        //really existing user?
        if (!empty($userData)) {
            //is user params acceptible to be online
            if ($this->userMustBeOnline($userData)) {
                //user isnt online right now.. just last check
                if (!$this->isUserOnlineRightNow($userLogin)) {
                    //trying to just reset user
                    $billing->resetuser($userLogin);
                    log_register("RESET User (" . $userLogin . ")");
                    sleep($this->waitTimeout);
                    //thats doesnt work?
                    if (!$this->isUserOnlineRightNow($userLogin)) {
                        //ok.. tryin to fix his cash value to real zero
                        if ($userData['Cash'] >= $this->lowerCashLimit) {
                            if ($userData['Cash'] < 0.01) {
                                zb_CashAdd($userLogin, 0, 'set', 1, 'KARMAFIX');
                                sleep($this->waitTimeout);
                                if (!$this->isUserOnlineRightNow($userLogin)) {
                                    //trying to reset user after cash correction
                                    $billing->resetuser($userLogin);
                                    log_register("RESET User (" . $userLogin . ")");
                                    sleep($this->waitTimeout);
                                    //we give up :(
                                    if (!$this->isUserOnlineRightNow($userLogin)) {
                                        $result .= __('We tried all that we can. Nothing helps. This user is doomed.');
                                    }
                                }
                            } else {
                                $result .= __('To much money') . ': ' . $userData['Cash'];
                            }
                        }
                    }
                }
            }
        } else {
            $result .= __('User not exists');
        }
        return($result);
    }

    /**
     * Renders report of users which possible have an bad karma
     * 
     * @return string
     */
    public function renderReport() {
        $result = '';
        $tmpArr = array();
        if (!empty($this->allUsersData)) {
            if (!empty($this->allOnlineUsers)) {
                foreach ($this->allUsersData as $eachUserLogin => $eachUserData) {
                    //may this user be just... online?
                    if ($this->userMustBeOnline($eachUserData)) {
                        //and he is not?
                        if (!$this->userIsOnline($eachUserLogin)) {
                            $tmpArr[$eachUserLogin] = $eachUserData; //yeah, fuck memory economy!
                        }
                    }
                }

                if (!empty($tmpArr)) {
                    $cells = wf_TableCell(__('Login'));
                    $cells .= wf_TableCell(__('Address'));
                    $cells .= wf_TableCell(__('Real Name'));
                    $cells .= wf_TableCell(__('IP'));
                    $cells .= wf_TableCell(__('Tariff'));
                    $cells .= wf_TableCell(__('Balance'));
                    $cells .= wf_TableCell(__('Credit'));
                    $cells .= wf_TableCell(__('Actions'));
                    $rows = wf_TableRow($cells, 'row1');

                    foreach ($tmpArr as $eachUserLogin => $eachUserData) {
                        $userLinkControl = wf_Link(self::URL_PROFILE . $eachUserLogin, web_profile_icon() . ' ' . $eachUserLogin);
                        $alertLabel = $this->messages->getEditAlert() . ' ' . __('Fix') . ' ' . $eachUserLogin . '?';
                        $repairLinkControl = wf_JSAlert(self::URL_ME . '&' . self::ROUTE_FIX . '=' . $eachUserLogin, wf_img('skins/icon_repair.gif'), $alertLabel);
                        $cells = wf_TableCell($userLinkControl);
                        $cells .= wf_TableCell($eachUserData['fulladress']);
                        $cells .= wf_TableCell($eachUserData['realname']);
                        $cells .= wf_TableCell($eachUserData['ip']);
                        $cells .= wf_TableCell($eachUserData['Tariff']);
                        $cells .= wf_TableCell($eachUserData['Cash']);
                        $cells .= wf_TableCell($eachUserData['Credit']);
                        $cells .= wf_TableCell($repairLinkControl);
                        $rows .= wf_TableRow($cells, 'row5');
                    }

                    $result .= wf_TableBody($rows, '100%', 0, 'sortable');
                } else {
                    $result .= $this->messages->getStyledMessage(__('Everything is good'), 'success');
                }
            } else {
                $result .= $this->messages->getStyledMessage(__('No online users at all'), 'warning');
            }
        } else {
            $result .= $this->messages->getStyledMessage(__('Nothing to show'), 'warning');
        }
        return($result);
    }

}
