<?php
/**
 * This file is part of the BehatBundle package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version //autogentag//
 */

namespace EzSystems\BehatBundle\ObjectManager;

use eZ\Publish\API\Repository\Values\ValueObject;
use eZ\Publish\API\Repository\Exceptions as ApiExceptions;
use Behat\Symfony2Extension\Context\KernelAwareContext;

class FieldType extends Base
{
    /**
     * Defines the state of the Construction object, if it's not published, partialy or completely published
     */
    const FIELD_TYPE_NOT_CREATED = -1;
    const FIELD_TYPE_CREATED = 0;
    const CONTENT_TYPE_CREATED = 1;
    const FIELD_TYPE_ASSOCIATED = 2;
    const CONTENT_TYPE_PUBLISHED = 3;
    const CONTENT_PUBLISHED = 4;

    /**
     * Default language
     */
    const DEFAULT_LANGUAGE = 'eng-GB';

    /**
     * @var array Stores the values needed to build the contentType with the desired fieldTypes, used to postpone until object is ready for publishing
     */
    private $fieldConstructionObject = array(
        "contentType" => null,
        "fieldType" => null,
        "content" => null,
        "objectState" => self::FIELD_TYPE_NOT_CREATED
    );

    /**
     * @var array Stores Internal mapping of the fieldType names
     */
    private $fieldTypeInternalIdentifier = array(
        "integer" => "ezinteger"
    );

    /**
     * @var array Maps the validator of the fieldtypes
     */
    private $validatorMappings = array(
        "integer" => "IntegerValue"
    );

    /**
     * @var array Maps the default values of the fieldtypes
     */
    private $defaultValues = array(
        "integer" => 1
    );

    /**
     * Getter method for fieldtype internal identifier
     *
     * @param string $identifier Identifier of the field
     * @return string internal Identifier of the field
     */
    public function getFieldTypeInternalIdentifier( $identifier )
    {
        return $this->fieldTypeInternalIdentifier[ $identifier ];
    }

    /**
     * Getter method for the validator mappings
     *
     * @param string $field Field name
     * @return string field Validator name
     */
    public function getFieldValidator( $field )
    {
        return $this->validatorMappings[ $field ];
    }

    /**
     * Creates a fieldtype ans stores it for later use
     *
     * @param string $fieldType Type of the field
     * @param string $name Name of the field, optional, if not specified $fieldType is used
     * @param boolean $required True if the is the field required, optional
     */
    public function createField( $fieldType, $name = null, $required = false )
    {
        $repository = $this->getRepository();
        $contentTypeService = $repository->getContentTypeService();
        $fieldPosition = $this->getActualFieldPosition();
        $name = ( $name == null ? $fieldType : $name );
        $fieldCreateStruct = $contentTypeService->newFieldDefinitionCreateStruct(
            $name,
            $this->fieldTypeInternalIdentifier[ $fieldType ]
        );
        $fieldCreateStruct->names = array( self::DEFAULT_LANGUAGE => $name );
        $fieldCreateStruct->position = $fieldPosition;
        $fieldCreateStruct->isRequired = $required;
        $fieldCreateStruct->defaultValue = $this->defaultValues[ $fieldType ];
        $this->fieldConstructionObject[ 'fieldType' ] = $fieldCreateStruct;
        $this->fieldConstructionObject[ 'objectState' ] = self::FIELD_TYPE_CREATED;
    }

    /**
     * Adds a validator to the stored field
     *
     * @param string $fieldType Type of the field
     * @param string $value Value of the constraint
     * @param string $constraint Constraint name
     */
    public function addValueConstraint( $fieldType, $value, $constraint )
    {
        $validatorName = $this->getFieldValidator( $fieldType );
        $validatorParent = $validatorName . "Validator";
        if ( $this->fieldConstructionObject[ 'fieldType' ]->validatorConfiguration == null )
        {
            $this->fieldConstructionObject[ 'fieldType' ]->validatorConfiguration = array(
                $validatorParent => array()
            );
        }
        $value = is_numeric( $value ) ? $value + 0 : $value;

        $this->fieldConstructionObject[ 'fieldType' ]->validatorConfiguration[ $validatorParent ][ $constraint . $validatorName ] = $value;
    }

    /**
     * Creates a content and publishes it
     *
     * @param string $field Name of the field
     * @param mixed $value Value of the field
     */
    public function createContent( $field, $value )
    {
        $this->setFieldContentState( self::CONTENT_PUBLISHED, $field, $value );
    }

    /**
     * Executes necessary operations to guarantee a given state, recursive
     * function that calls it self to make sure prerequisites are met
     *
     * @param int $stateFlag Desired state, only predefined constants accepted
     * @param string $field Name of the field, optional
     * @param mixed $value Value of the field, optional
     */
    public function setFieldContentState( $stateFlag, $field = null, $value = null )
    {
        if ( $stateFlag <= $this->fieldConstructionObject[ 'objectState' ] || $stateFlag < self::FIELD_TYPE_NOT_CREATED )
        {
            return;
        }

        // recursively set previous states if necessary
        $this->setFieldContentState( $stateFlag - 1, $field, $value );

        switch( $stateFlag )
        {
            case self::FIELD_TYPE_NOT_CREATED:
                throw new \Exception( 'A field type must be declared before anything else' );
                break;
            case self::CONTENT_TYPE_CREATED:
                $this->createContentType();
                break;
            case self::FIELD_TYPE_ASSOCIATED:
                $this->associateFieldToContentType();
                break;
            case self::CONTENT_TYPE_PUBLISHED:
                $this->publishContentType();
                break;
            case self::CONTENT_PUBLISHED:
                $this->publishContent( $field, $value );
                break;
        }
    }

    public function getFieldContentState()
    {
        return $this->fieldConstructionObject[ 'objectState' ];
    }

    /**
     * Publishes the content
     *
     * @param string The field name
     * @param mixed The field value
     */
    private function publishContent( $field, $value )
    {
        $repository = $this->getRepository();
        $languageCode = self::DEFAULT_LANGUAGE;

        $content = $repository->sudo(
            function() use( $repository, $languageCode, $field, $value )
            {
                $contentService = $repository->getcontentService();
                $locationCreateStruct = $repository->getLocationService()->newLocationCreateStruct( '2' );
                $contentType = $this->fieldConstructionObject[ 'contentType' ];
                $contentCreateStruct = $contentService->newContentCreateStruct( $contentType, $languageCode );
                if ( $field != null && $value != null )
                {
                    $value = ( $value == 'empty'  ) ? null : $value;
                    $value = is_numeric( $value ) ? $value + 0 : $value;
                    $contentCreateStruct->setField( $field, $value );
                }
                $draft = $contentService->createContent( $contentCreateStruct, array( $locationCreateStruct ) );
                $content = $contentService->publishVersion( $draft->versionInfo );

                return $content;
            }
        );
        $this->fieldConstructionObject[ 'content' ] = $content;
        $this->fieldConstructionObject[ 'objectState' ] = self::CONTENT_PUBLISHED;
    }

    /**
     * Associates the stored fieldtype to the stored contenttype
     */
    private function associateFieldToContentType()
    {
        $fieldCreateStruct = $this->fieldConstructionObject[ 'fieldType' ];
        $this->fieldConstructionObject[ 'contentType' ]->addFieldDefinition( $fieldCreateStruct );
        $this->fieldConstructionObject[ 'objectState' ] = self::FIELD_TYPE_ASSOCIATED;
    }

    /**
     * Publishes the stored contenttype
     */
    private function publishContentType()
    {
        $repository = $this->getRepository();
        $repository->sudo(
            function() use( $repository )
            {
                $contentTypeService = $repository->getContentTypeService();
                $contentTypeGroup = $contentTypeService->loadContentTypeGroupByIdentifier( 'Content' );
                $contentTypeCreateStruct = $this->fieldConstructionObject[ 'contentType' ];
                $contentTypeDraft = $contentTypeService->createContentType( $contentTypeCreateStruct, array( $contentTypeGroup ) );
                $contentTypeService->publishContentTypeDraft( $contentTypeDraft );
            }
        );
        $contentTypeIdentifier = $this->fieldConstructionObject[ 'contentType' ]->identifier;
        $contentType = $repository->getContentTypeService()->loadContentTypeByIdentifier( $contentTypeIdentifier );
        $this->fieldConstructionObject[ 'contentType' ] = $contentType;
        $this->fieldConstructionObject[ 'objectState' ] = self::CONTENT_TYPE_PUBLISHED;
    }

    /**
     * Getter method for the name of the stored contenttype
     *
     * @param string $language Language of the name
     * @return string Name of the contenttype
     */
    public function getThisContentTypeName( $language = self::DEFAULT_LANGUAGE )
    {
        return $this->fieldConstructionObject[ 'contentType' ]->names[ $language ];
    }

    /**
     * Getter method for the name of the stored content
     *
     * @param string $language Language of the name
     * @return string Name of the contenttype
     */
    public function getThisContentName( $language = self::DEFAULT_LANGUAGE )
    {
        return $this->fieldConstructionObject[ 'content' ]->versionInfo->names[ $language ];
    }

    /**
     * Getter method for the name of the stored fieldtype
     *
     * @param string $language Language of the name
     * @return string Name of the fieldtype
     */
    public function getThisFieldTypeName( $language = self::DEFAULT_LANGUAGE )
    {
        return $this->fieldConstructionObject[ 'fieldType' ]->names[ $language ];
    }

    /**
     * Getter method for the identifier of the stored fieldtype
     *
     * @return string idenfier of the fieldtype
     */
    public function getThisFieldTypeIdentifier()
    {
        return $this->fieldConstructionObject[ 'fieldType' ]->identifier;
    }

    /**
     * Get the content id for the published content
     *
     * @return int
     */
    public function getThisContentId()
    {
        return $this->fieldConstructionObject[ 'content' ]->id;
    }


    /**
     * Creates an instance of a contenttype and stores it for later publishing
     */
    private function createContentType()
    {
        $repository = $this->getRepository();
        $contentTypeService = $repository->getContentTypeService();
        $name = $this->fieldConstructionObject[ 'fieldType' ]->identifier;
        $name .= "#" . rand( 1000, 9000 );
        $identifier = strtolower( $name );
        $contentTypeCreateStruct = $contentTypeService->newContentTypeCreateStruct( $identifier );
        $contentTypeCreateStruct->mainLanguageCode = self::DEFAULT_LANGUAGE;
        $contentTypeCreateStruct->names = array( self::DEFAULT_LANGUAGE => $name );
        $contentTypeCreateStruct->nameSchema = $name;
        $this->fieldConstructionObject[ 'contentType' ] = $contentTypeCreateStruct;
        $this->fieldConstructionObject[ 'objectState' ] = self::CONTENT_TYPE_CREATED;
    }

    /**
     * Getter method for the position of the field, relative to other possible fields
     */
    private function getActualFieldPosition()
    {
        if ( $this->fieldConstructionObject[ 'fieldType' ] == null )
        {
            return 10;
        }
        else
        {
            return $this->fieldConstructionObject[ 'fieldType' ]->position + 10;
        }
    }

    /**
     * NOT USED FOR NOW
     */
    protected function destroy( ValueObject $object )
    {
    // do nothing for now, to be implemented later when decided waht to do with the created objects
    // must be empty because this method allways runs
    }
}
