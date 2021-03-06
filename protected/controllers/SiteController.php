<?php
class SiteController extends Controller {
  public function actionIndex() {
    if (Yii::app()->user->name !== 'Guest') {
      $this->layout = '//layouts/columnadmin';
      Yii::app()->theme = Yii::app()->user->themename;
      $this->render('index');
    } else {
      $this->actionLogin();
    }
  }
  public function actionError() {
  }
  public function actionAbout() {
    $this->render('about');
	}
	public function actionGetUserLogin(){
		echo Yii::app()->user->username;
	}
  public function actionSaveProfile() {
    $model = Yii::app()->db->createCommand("select useraccessid,username,password from useraccess where username = '" . $_POST['username'] . "'")->queryRow();
		$connection=Yii::app()->db;
    if ($model['password'] !== $_POST['password']) {
			$sql = "update useraccess 
				set password = md5(:vpassword), phoneno = :vphoneno, 
				email = :vemail, realname = :vrealname, languageid = :vlanguageid, themeid = :vthemeid 
				where username = :vusername";
			$command=$connection->createCommand($sql);
			$command->bindvalue(':vpassword',$_POST['password'],PDO::PARAM_STR);
			Yii::app()->user->password = $_POST['password'];
    } else {
			$sql = "update useraccess 
				set phoneno = :vphoneno, 
				email = :vemail, realname = :vrealname, languageid = :vlanguageid, themeid = :vthemeid 
				where username = :vusername";
			$command=$connection->createCommand($sql);
		}
		$command->bindvalue(':vphoneno',$_POST['phoneno'],PDO::PARAM_STR);
		$command->bindvalue(':vemail',$_POST['email'],PDO::PARAM_STR);
		$command->bindvalue(':vrealname',$_POST['realname'],PDO::PARAM_STR);
		$command->bindvalue(':vlanguageid',$_POST['languageid'],PDO::PARAM_STR);
		$command->bindvalue(':vthemeid',$_POST['themeid'],PDO::PARAM_STR);
		$command->bindvalue(':vusername',$_POST['username'],PDO::PARAM_STR);
		$command->execute();
		Yii::app()->user->realname = $_POST['realname'];
		Yii::app()->user->email = $_POST['email'];
		Yii::app()->user->phoneno = $_POST['phoneno'];
		Yii::app()->user->languageid = $_POST['languageid'];
		Yii::app()->user->themeid = $_POST['themeid'];
		GetMessage(false, getcatalog('insertsuccess'));
  }
  public function actionHome() {		
    $this->render('home');
  }
  public function actionLogin() {
    $this->layout = '//layouts/columngeneral';
		$model        = new LoginForm;
    if (isset($_POST['ajax']) && $_POST['ajax'] === 'login-form') {
      echo CActiveForm::validate($model);
      Yii::app()->end();
    }
    if (isset($_POST['LoginForm'])) {
      $model->attributes = $_POST['LoginForm'];
      if ($model->validate() && $model->login()) {
        $this->redirect(Yii::app()->user->returnUrl);
      }
    }
    $this->render('login', array(
      'model' => $model
    ));
  }
  public function actionLogout() {
		Yii::app()->db->createCommand("update useraccess set isonline = 0 where username = '" . Yii::app()->user->id . "'")->execute();
    Yii::app()->user->logout();
    $this->redirect(Yii::app()->user->returnUrl);
  }
	public function actionGetHistoryData() {
		header("Content-Type: application/json");
		$menuname = filter_input(INPUT_POST,'menuname');
		$tableid = filter_input(INPUT_POST,'tableid');
		$result     = array();
    $row        = array();
		$cmd = Yii::app()->db->createCommand()->select('t.translogid,t.createddate,t.username,t.useraction,
			t.newdata,t.olddata,t.menuname,t.tableid,t.ippublic,t.iplocal,t.lat,t.lng')
			->from('translog t')
			->where("
				(coalesce(menuname,'') = :menuname) 
				and (coalesce(tableid,'') = :tableid) 
				",
				array(
				':menuname' =>  $menuname ,
				':tableid' =>  $tableid ,
				))->order('createddate desc')->queryAll();
		foreach ($cmd as $data) {
			$row[] = array(
				'translogid' => $data['translogid'],
				'createddate' => date(Yii::app()->params['datetimeviewfromdb'], strtotime($data['createddate'])),
				'username' => $data['username'],
				'useraction' => $data['useraction'],
				'newdata' => $data['newdata'],
				'olddata' => $data['olddata'],
				'menuname' => $data['menuname'],
				'tableid' => $data['tableid'],
				'ippublic' => $data['ippublic'],
				'iplocal' => $data['iplocal'],
				'lat' => $data['lat'],
				'lng' => $data['lng'],
      );
		}
		$result = array_merge($result, array(
			'rows' => $row
		));
		echo CJSON::encode($result);			
  }
  public function actionGetCode() {
    $menuname = $_POST['menuname'];
    try {
      $sql = "select viewcode,controllercode,b.modulename,ifnull(menudep,'') as menudep
        from menuaccess a
        join modules b on b.moduleid = a.moduleid 
        where menuname = '".$menuname."'";
      $cmd = Yii::app()->db->createCommand($sql)->queryRow();
      $foldermodule = Yii::getPathOfAlias('webroot')."/protected/modules/".$cmd['modulename'];
      if (!file_exists($foldermodule)) {
        mkdir($foldermodule, 0777);
      }
      $foldercontroller = Yii::getPathOfAlias('webroot')."/protected/modules/".$cmd['modulename']."/controllers";
      if (!file_exists($foldercontroller)) {
        mkdir($foldercontroller, 0777);
      }
      $filecontroller = Yii::getPathOfAlias('webroot')."/protected/modules/".$cmd['modulename']."/controllers/".ucfirst($menuname)."Controller.php";
      $fh = fopen($filecontroller, 'w');
      fwrite($fh, $cmd['controllercode']."\n");
      fclose($fh);

      $folderview = Yii::getPathOfAlias('webroot')."/protected/modules/".$cmd['modulename']."/views";
      if (!file_exists($folderview)) {
        mkdir($folderview, 0777);
      }
      $foldermenu = Yii::getPathOfAlias('webroot')."/protected/modules/".$cmd['modulename']."/views/".$menuname;
      if (!file_exists($foldermenu)) {
        mkdir($foldermenu, 0777);
      }
      $fileview = Yii::getPathOfAlias('webroot')."/protected/modules/".$cmd['modulename']."/views/".$menuname."/index.php";
      $fh = fopen($fileview, 'w');
      fwrite($fh, $cmd['viewcode']."\n");
      fclose($fh);

      $sql = "update menuaccess set isgen = 1 where menuname = '".$menuname."'";
      Yii::app()->db->createCommand($sql)->execute();
      if ($cmd['menudep'] != '') {
        $menudeps = explode(",",$cmd['menudep']);
        foreach ($menudeps as $menudep) {
          CreateCode($menudep);
        }
      }
      GetMessage(false,getcatalog('insertsuccess'));
    } 
    catch (Exception $ex) {
      GetMessage(true,$ex->errorInfo);
    }
  }
}