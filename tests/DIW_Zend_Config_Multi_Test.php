<?php

class DIW_Zend_Config_Multi_Test extends PHPUnit_Framework_TestCase
{
    public function testINIGeneration()
    {
        $multi = new DIW_Zend_Config_Multi(true);
        $multi->attach( new Zend_Config(array(
            'sectionA' => array(
                'Aa' => 'one-Aa',
                'Bb' => 'one-Bb'
            )
        )) );
        $multi->overrideDirty( new Zend_Config(array(
            'sectionA' => array(
                'Aa' => 'two-Aa',
                'Ac' => 'two-Ac',
                'Ad' => 'two-Ad'
            )
        )) );
        $multi->sectionA->Ad = 'three-Ad';
        $multi->sectionB = new Zend_Config(array('Ba' => 'three-Ba'));

        $writer = new Zend_Config_Writer_Ini(array('config' => $multi->getDirty()));

        $this->assertEquals(
            trim(
                "[sectionA]\n".
                "Aa = \"two-Aa\"\n".
                "Ac = \"two-Ac\"\n".
                "Ad = \"three-Ad\"\n".
                "\n".
                "[sectionB]\n".
                "Ba = \"three-Ba\"\n"
            ),
            trim($writer->render()),
            'render()ing an INI file based on a DIW_Zend_Config_Multi should '.
            'skip non-dirty sections, include "writeable" data, and output '.
            'the correct data based on overrides'
        );
    }

    public function testAttachmentOfOneAllowsGet()
    {
        $multi = new DIW_Zend_Config_Multi();
        $multi->attach( new Zend_Config(array(
            'aKey' => 'aValue'
        )) );

        $this->assertEquals('aValue', $multi->get('aKey'));
    }

    public function testOverrideAfterAttachGetsOverride()
    {
        $multi = new DIW_Zend_Config_Multi();
        $multi->attach( new Zend_Config(array(
            'aKey' => 'aValue'
        )) );

        $multi->override( new Zend_Config(array(
            'aKey' => 'aNotherValue'
        )) );

        $this->assertEquals('aNotherValue', $multi->get('aKey'));
    }

    public function testAttachmentAndOverrideToArrayMerges()
    {
        $multi = new DIW_Zend_Config_Multi();
        $multi->attach( new Zend_Config(array(
            'aKey' => 'one-aValue',
            'bKey' => 'one-bValue',
            'deep' => array(
                'deep-aKey' => 'deep-one-aValue',
                'deep-bKey' => 'deep-one-bValue'
            )
        )) );

        $multi->override( new Zend_Config(array(
            'aKey' => 'two-aValue',
            'deep' => array(
                'deep-aKey' => 'deep-two-aValue'
            )
        )) );

        $this->assertEquals(array(
            'aKey' => 'two-aValue',
            'bKey' => 'one-bValue',
            'deep' => array(
                'deep-aKey' => 'deep-two-aValue',
                'deep-bKey' => 'deep-one-bValue'
            )
        ), $multi->toArray(false));
    }

    public function testAttachmentOfMultipleToArrayMergesOnlyDirty()
    {
        $multi = new DIW_Zend_Config_Multi(true);
        $multi->attach( new Zend_Config(array(
            'aKey' => 'one-aValue',
            'bKey' => 'one-bValue',
            'deep' => array(
                'deep-aKey' => 'deep-one-aValue',
                'deep-bKey' => 'deep-one-bValue'
            )
        )) );

        $multi->overrideDirty( new Zend_Config(array(
            'aKey' => 'two-aValue',
            'cKey' => 'two-cValue',
            'deep' => array(
                'deep-aKey' => 'deep-two-aValue',
                'deep-cKey' => 'deep-two-cValue'
            )
        )) );

        $multi->writeableKey = 'aWriteableValue';
        $multi->writeableDeep = array('aWriteableDeepKey' => 'aWriteableDeepValue');
        $multi->cKey = 'writeable-cValue';
        $multi->deep = array();
        $multi->deep->{'deep-cKey'} = 'deep-writeable-cValue';

        $this->assertEquals(array(
            'aKey' => 'two-aValue',
            'cKey' => 'writeable-cValue',
            'writeableKey' => 'aWriteableValue',
            'writeableDeep' => array(
                'aWriteableDeepKey' => 'aWriteableDeepValue'
            ),
            'deep' => array(
                'deep-aKey' => 'deep-two-aValue',
                'deep-cKey' => 'deep-writeable-cValue'
            )
        ), $multi->toDirtyArray());
    }

    public function testWriteOfMultiAndOriginal()
    {
        $orig = new Zend_Config(array(), true);
        $orig->subConfig = new Zend_Config(array(), true);
        $orig->subConfig->aKey = 'deepUnmodifiedValue';
        $orig->aKey = 'unmodifiedValue';

        $multi = new DIW_Zend_Config_Multi(true);
        $multi->attach( $orig );
        $multi->subConfig->aKey = 'deepModifiedValue';
        $multi->aKey = 'modifiedValue';

        $this->assertEquals('modifiedValue', $multi->aKey,
            'Modification of Multi should be visible from Multi');
        $this->assertEquals('unmodifiedValue', $orig->aKey,
            'Modification of Multi should not be visible from Original');
        $this->assertEquals('deepModifiedValue', $multi->subConfig->aKey,
            'Deep Modification of Multi should be visible from Multi');
        $this->assertEquals('deepUnmodifiedValue', $orig->subConfig->aKey,
            'Deep Modification of Multi should not be visible from Original');

        $orig->subConfig->bKey = 'deepUnmodifiedValueB';
        $orig->newSubConfig = new Zend_Config(array(
            'aKey' =>'deepNewUnmodifiedValueA'
        ));
        $orig->bKey = 'unmodifiedValueB';
        $this->assertEquals('unmodifiedValueB', $multi->bKey,
            'Value added to Original should be visible from Multi');
        $this->assertEquals('deepUnmodifiedValueB', $multi->subConfig->bKey,
            'Deep Value added to Original should be visible from Multi');
        $this->assertEquals('deepNewUnmodifiedValueA', $multi->newSubConfig->aKey,
            'New Deep Value added to Original post-attach() should be visible '.
            'from Multi');
    }
}
