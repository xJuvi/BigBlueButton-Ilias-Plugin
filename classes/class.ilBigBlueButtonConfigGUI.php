<?php

include_once("./Services/Component/classes/class.ilPluginConfigGUI.php");



/**
 * BigBlueButton configuration class
 *
 * @version $Id$
 * 
 *@ilCtrl_isCalledBy srag\DevTools\DevToolsCtrl: ilBigBlueButtonConfigGUI
 */
class ilBigBlueButtonConfigGUI extends ilPluginConfigGUI
{

    const PLUGIN_CLASS_NAME = ilBigBlueButtonPlugin::class;

	private $pl_object;
    /**
    * Handles all commmands, default is "configure"
    */
	function performCommand($cmd)
	{

		switch ($cmd)
		{
			case "configure":
			case "save":
				$this->$cmd();
				break;

		}
	}

    /**
     * Configure screen
     */
    public function configure()
    {
        global $tpl;

        $form = $this->initConfigurationForm();
        $tpl->setContent($form->getHTML());
    }


    /**
     * Init configuration form.
     *
     * @return object form object
     */
    public function initConfigurationForm()
    {
        global $lng, $ilCtrl, $ilDB;

        $values = array();
        $result = $ilDB->query("SELECT * FROM rep_robj_xbbb_conf");
        while ($record = $ilDB->fetchAssoc($result)) {
            $values["svrpublicurl"] = $record["svrpublicurl"];
            $values["svrprivateurl"] = $record["svrprivateurl"];
            $values["svrsalt"] = $record["svrsalt"];
            $values["choose_recording"] = $record["choose_recording"];
            $values["guest_global_choose"] = $record["guestglobalchoose"];
        }


        $pl = $this->getPluginObject();
        if ($values["svrpublicurl"] != '' && $values["svrprivateurl"] != '' && $values["svrsalt"] != '') {
            $server_reachable=$this->isServerReachable($values["svrpublicurl"], $values["svrsalt"]);
            if (!$server_reachable) {
                ilUtil::sendFailure("server not reachable", true);
            }
        }

        include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
        $form = new ilPropertyFormGUI();

        //

        // public url (text)
        $ti = new ilTextInputGUI($pl->txt("publicurl"), "frmpublicurl");
        $ti->setRequired(true);
        $ti->setMaxLength(256);
        $ti->setSize(60);
        $ti->setValue($values["svrpublicurl"]);
        $form->addItem($ti);

        // private url (text)
        $ti = new ilTextInputGUI($pl->txt("privateurl"), "frmprivateurl");
        $ti->setRequired(true);
        $ti->setMaxLength(256);
        $ti->setSize(60);
        $ti->setValue($values["svrprivateurl"]);
        $form->addItem($ti);

        // salt (text)
        $pi = new ilPasswordInputGUI($pl->txt("salt"), "frmsalt");
        $pi->setRequired(true);
        $pi->setSkipSyntaxCheck(true);
        $pi->setMaxLength(256);
        $pi->setSize(40);
        $pi->setRetype(false);
        $pi->setValue($values["svrsalt"]);
        $form->addItem($pi);

        //recording configuration
        $choose_recording = new ilCheckboxInputGUI($pl->txt("choose_recording"), "choose_recording");
        $choose_recording->setRequired(false);
        $choose_recording->setInfo($pl->txt("choose_recording_info"));
        $choose_recording->setChecked((int) $values['choose_recording']);
        $form->addItem($choose_recording);
        
        //Guest
        $guest_global_choose = new ilCheckboxInputGUI($pl->txt("guest_global_choose"), "guest_global_choose");
        $guest_global_choose->setRequired(false);
        $guest_global_choose->setInfo($pl->txt("guest_global_choose_info"));
        $guest_global_choose->setChecked((int) $values['guest_global_choose']);
        $form->addItem($guest_global_choose);


        $form->addCommandButton("save", $lng->txt("save"));

        $form->setTitle($pl->txt("BigBlueButton_plugin_configuration"));
        $form->setFormAction($ilCtrl->getFormAction($this));

        return $form;
    }
    /**
     * Save form input
     *
     */
    public function save()
    {
        global $tpl, $lng, $ilCtrl, $ilDB;

        $pl = $this->getPluginObject();

        $form = $this->initConfigurationForm();
        if ($form->checkInput()) {
            $setPublicURL = $this->checkUrl($form->getInput("frmpublicurl"));
            $setPrivateURL = $this->checkUrl($form->getInput("frmprivateurl"));
            $setSalt= $form->getInput("frmsalt");
            $choose_recording = (int) $form->getInput("choose_recording");
            $guest_global_choose = (int) $form->getInput("guest_global_choose");

            // check if data exisits decide to update or insert
            $result = $ilDB->query("SELECT * FROM rep_robj_xbbb_conf");
            $num = $ilDB->numRows($result);
            if ($num == 0) {
                $ilDB->manipulate("INSERT INTO rep_robj_xbbb_conf ".
                "(id, svrpublicurl , svrprivateurl, svrsalt, choose_recording, guestglobalchoose) VALUES (".
                $ilDB->quote(1, "integer").",". // id
                $ilDB->quote($setPublicURL, "text").",". //public url
                $ilDB->quote($setPrivateURL, "text").",". //private url

                $ilDB->quote($setSalt, "text").",". //salt
                $ilDB->quote($choose_recording, "integer").",".
                $ilDB->quote($guest_global_choose, "integer").
                ")");
            } else {
                $ilDB->manipulate(
                    $up = "UPDATE rep_robj_xbbb_conf  SET ".
                " svrpublicurl = ".$ilDB->quote($setPublicURL, "text").",".
                " svrprivateurl = ".$ilDB->quote($setPublicURL, "text").",".
                " svrsalt = ".$ilDB->quote($setSalt, "text"). ",".
                " choose_recording = ".$ilDB->quote($choose_recording, "integer"). ",".
                "guestglobalchoose = ". $ilDB->quote($guest_global_choose, "integer").
                " WHERE id = ".$ilDB->quote(1, "integer")
                );
            }

            ilUtil::sendSuccess($pl->txt("saving_invoked"), true);
            $ilCtrl->redirect($this, "configure");
        } else {
            $form->setValuesByPost();
            $tpl->setContent($form->getHtml());
        }
    }

    private function checkUrl(string $url)
    {
        if (substr($url, -1)!="/") {
            $url .="/";
        }
        return $url;
    }

    private function isServerReachable(string $url, string $salt)
    {
        include_once("./Customizing/global/plugins/Services/Repository/RepositoryObject/BigBlueButton/classes/class.ilBigBlueButtonProtocol.php");
        $bbb_helper=new BBB($salt,$url);
        try{
            $bbb_helper->getApiVersion();
        }catch (Exception $e) {
            return false;
        }
        return true;

    }


}
