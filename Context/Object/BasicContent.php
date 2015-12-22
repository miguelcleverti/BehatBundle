<?php
/**
  *This file is part of the BehatBundle package
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version //autogentag//
 */

namespace EzSystems\BehatBundle\Context\Object;

use Behat\Gherkin\Node\TableNode;
use PHPUnit_Framework_Assert as Assertion;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;

/**
 * Sentences for Fields
 *
 * @method \EzSystems\BehatBundle\ObjectManager\BasicContent getBasicContentManager
 */
trait BasicContent
{
    /**
     * @Given a/an :path folder exists
     */
    public function createBasicFolder( $path )
    {
        $fields = array( 'name' => $this->getTitleFromPath( $path ) );
        return $this->getBasicContentManager()->createContentwithPath( $path, $fields, 'folder' );
    }

    /**
     * @Given a/an :path article exists
     */
    public function createBasicArticle( $path )
    {
        $fields = array(
            'title' => $this->getTitleFromPath( $path ),
            'intro' => $this->getDummyXmlText()
        );
        return $this->getBasicContentManager()->createContentwithPath( $path, $fields, 'article' );
    }

    /**
     * @Given a/an :path article draft exists
     */
    public function createArticleDraft( $path )
    {
        $fields = array(
            'title' => $this->getTitleFromPath( $path ),
            'intro' => $this->getDummyXmlText()
        );
        return $this->getBasicContentManager()->createContentDraft( 2, 'article', $fields );
    }

    private function getTitleFromPath( $path )
    {
        $parts = explode( '/', rtrim( $path, '/' ) );
        return end( $parts );
    }

    /**
     * @Given I add the :location content as location to the content :content
     */
    public function addLocation($location, $content) {
        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();
        $criterion = new Criterion\LogicalOr(
            array(
                new Criterion\Field("name", Criterion\Operator::EQ, $location),
                new Criterion\Field("title", Criterion\Operator::EQ, $location)
            )
        );
        $query = new Query();
        $query->query = $criterion;
        $searchResult = $searchService->findContent($query);
        $folderContent = $searchResult->searchHits[0]->valueObject;
        $criterion = new Criterion\LogicalOr(
            array(
                new Criterion\Field("title", Criterion\Operator::EQ, $content),
                new Criterion\Field("name", Criterion\Operator::EQ, $content)
            )
        );
        $query->query = $criterion;
        $searchResult = $searchService->findContent($query);
        $articleContent = $searchResult->searchHits[0]->valueObject;

        $repository->sudo(
            function () use ($repository, $folderContent, $articleContent) {
                $contentService = $repository->getContentService();
                $locationService = $repository->getLocationService();
                $folderContentInfo = $contentService->loadContentInfo($folderContent->id);
                $locationCreateStruct = $locationService->newLocationCreateStruct($folderContentInfo->mainLocationId);
                $articleContentInfo = $contentService->loadContentInfo($articleContent->id);
                $newLocation = $locationService->createLocation($articleContentInfo, $locationCreateStruct);
            }
        );
    }

    /**
     * @return string
     */
    private function getDummyXmlText()
    {
        return '<?xml version="1.0" encoding="UTF-8"?><section xmlns="http://docbook.org/ns/docbook" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:ezxhtml="http://ez.no/xmlns/ezpublish/docbook/xhtml" xmlns:ezcustom="http://ez.no/xmlns/ezpublish/docbook/custom" version="5.0-variant ezpublish-1.0"><para>This is a paragraph.</para></section>';
    }
}
