<?php
/**
 * PHPOpenBiz Framework
 *
 * LICENSE
 *
 * This source file is subject to the BSD license that is bundled
 * with this package in the file LICENSE.txt.
 *
 * @package   openbiz.bin.easy
 * @copyright Copyright &copy; 2005-2009, Rocky Swen
 * @license   http://www.opensource.org/licenses/bsd-license.php
 * @link      http://www.phpopenbiz.org/
 * @version   $Id$
 */

/**
 * EasyFormWizard class, extension of EasyForm to support wizard form
 *
 * @package openbiz.bin.easy
 * @author Rocky Swen
 * @copyright Copyright (c) 2005-2009
 * @access public
 */
class EasyFormWizard extends EasyForm
{
    protected $m_DropSession = false;

    /**
     * Get/Retrieve Session data of this object
     *
     * @param SessionContext $sessionContext
     * @return void
     */
    public function getSessionVars($sessionContext)
    {
        $sessionContext->getObjVar($this->m_Name, "ActiveRecord", $this->m_ActiveRecord, true);
        $sessionContext->getObjVar($this->m_Name, "FormInputs", $this->m_FormInputs, true);
        $this->setActiveRecord($this->m_ActiveRecord);
    }

    /**
     * Save object variable to session context
     *
     * @param SessionContext $sessionContext
     * @return void
     */
    public function setSessionVars($sessionContext)
    {
        if ($this->m_DropSession)
            $sessionContext->cleanObj($this->m_Name, true);
        else {
            $sessionContext->setObjVar($this->m_Name, "ActiveRecord", $this->m_ActiveRecord, true);
            $sessionContext->setObjVar($this->m_Name, "FormInputs", $this->m_FormInputs, true);
        }
    }

    /**
     * Go to next wizard page
     *
     * @param boolean $commit true if need to commit current form data
     * @return void
     * @access remote
     */
    public function goNext($commit=false)
    {
        // call ValidateForm()
        $recArr = $this->readInputRecord();
        $this->setActiveRecord($recArr);
    	
   		 try
        {
             if ($this->ValidateForm() == false)
            return;
        }catch (ValidationException $e)
        {
            $this->processFormObjError($e->m_Errors);
            return;
        }

        $this->m_ActiveRecord = $this->readInputRecord();
		$viewObj = $this->getViewObject();
        // get the step
    	if($viewObj->getCurrentStep()){
        	$step = $viewObj->getCurrentStep();
        }else{
        	$step = $_GET['step'];
        }
        if (!$step || $step=="")
            $step=1;

        // redirect the prev step
        /* @var $viewObj EasyViewWizard */
        
        $viewObj->renderStep($step+1);
    }

    /**
     * Go to previous wizard page
     *
     * @return void
     * @access remote
     */
    public function goBack()
    {
        // call ValidateForm()
        $recArr = $this->readInputRecord();
        $this->setActiveRecord($recArr);
    	
   		 try
        {
             if ($this->ValidateForm() == false)
            return;
        }catch (ValidationException $e)
        {
            $this->processFormObjError($e->m_Errors);
            return;
        }

        $this->m_ActiveRecord = $this->readInputRecord();

        $viewObj = $this->getViewObject();
        
        // get the step
        if($viewObj->getCurrentStep()){
        	$step = $viewObj->getCurrentStep();
        }else{
        	$step = $_GET['step'];
        }

        // redirect the prev step
        /* @var $viewObj EasyViewWizard */
        
        $viewObj->renderStep($step-1);
    }

    /**
     * Finish the wizard process
     *
     * @return void
     * @access remote
     */
    public function doFinish() //- call FinishWizard() by default

    {
        // call ValidateForm()
        $recArr = $this->readInputRecord();
        $this->setActiveRecord($recArr);                
    	$this->setFormInputs($this->m_FormInputs);
    	
   		 try
        {
             if ($this->ValidateForm() == false)
            return;
        }catch (ValidationException $e)
        {                    	
        	$this->processFormObjError($e->m_Errors);
            return;
        }

        $this->m_ActiveRecord = $this->readInputRecord();
		
        /* @var $viewObj EasyViewWizard */
        $viewObj = $this->getViewObject();

        $r = $viewObj->commit();
        if (!$r)
            return;

        $this->processPostAction();
    }

    /**
     * Cancel the wizard process
     *
     * @return void
     * @access remote
     */
    public function doCancel() //- call CancelWizard() by default

    {
        /* @var $viewObj EasyViewWizard */
        $viewObj = $this->getViewObject();
        $viewObj->cancel();

        $this->processPostAction();
    }

    /**
     * Save wizard data of current+previous pages into database or other storage
     *
     * @return void
     */
    public function commit()
    {
        // commit the form input. call SaveRecord()
        $recArr = $this->m_ActiveRecord;

        if ($this->m_FormType == "NEW")
            $dataRec = new DataRecord(null, $this->getDataObj());
        else
        {
            //$currentRec = $this->fetchData(); // wrong way to get current data. need to query the old one
            $currentRec = array(); // to get record with "" values
            $dataRec = new DataRecord($currentRec, $this->getDataObj());
        }

        foreach ($recArr as $k => $v)
            $dataRec[$k] = $v; // or $dataRec->$k = $v;
        try
        {
            $dataRec->save();
        } catch (BDOException $e)
        {
            $this->processBDOException($e);
            return false;
        }

        return true;
    }

    public function dropSession(){
    	// clean the session record    	
        $this->m_DropSession = true;
    }    
    

    /**
     * Clean up the sessions of view and all forms
     *
     * @return void
     */
    public function cancel()
    {
        // clean the session record
        $this->m_DropSession = true;
    }

    /**
     * Render this form
     *
     * @return @return string - HTML text of this form's read mode
     */
    public function render()
    {
        $viewobj = $this->getViewObject();
        $viewobj->setFormState($this->m_Name, 'visited', 1);

        return parent::render();
    }
    public function outputAttrs()
    {
        $output = parent::outputAttrs();
        $viewobj = $this->getViewObject();
        $forms = array();
        $viewobj->m_FormRefs->rewind();
        while($viewobj->m_FormRefs->valid()){
        	$form=$viewobj->m_FormRefs->current();
        	$forms[$form->m_Name] = $form;
        	$viewobj->m_FormRefs->next();
        }
        $output['forms'] = $forms;
        $output['step'] = $viewobj->getCurrentStep();        
        return $output;
    }    
}
?>