<?php
/*
 * Copyright 2018 Jérôme Gasperi
 *
 * Licensed under the Apache License, version 2.0 (the "License");
 * You may not use this file except in compliance with the License.
 * You may obtain a copy of the License at:
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

/**
 * Features API
 */
class FeaturesAPI
{
    private $context;
    private $user;

    /**
     * Constructor
     */
    public function __construct($context, $user)
    {
        $this->context = $context;
        $this->user = $user;
    }

    /**
     * Return feature
     *
     * @OA\Get(
     *      path="/collections/{collectionName}/items/{featureId}.{format}",
     *      summary="Get feature",
     *      description="Returns feature {featureId} metadata",
     *      tags={"Feature"},
     *      @OA\Parameter(
     *         name="collectionName",
     *         in="path",
     *         required=true,
     *         description="Collection name",
     *         @OA\Schema(
     *             type="string"
     *         )
     *      ),
     *      @OA\Parameter(
     *          name="featureId",
     *          in="path",
     *          description="Feature identifier",
     *          required=true,
     *          @OA\Schema(
     *             type="string"
     *         )
     *      ),
     *      @OA\Parameter(
     *          name="format",
     *          in="path",
     *          description="Output format of the feature - *json* or *atom*",
     *          required=false,
     *          @OA\Items(
     *              type="string",
     *              enum={"json", "atom"}
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="fields",
     *          in="query",
     *          description="Comma separated list of property fields to be returned",
     *          required=false,
     *          @OA\Items(
     *              type="string"
     *          ),
     *          description="Comma separated list of property fields to be returned
* _all: Return all properties
* _default: Return all fields except *keywords* property"
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Feature metadata",
     *          @OA\JsonContent(ref="#/components/schemas/OutputFeature")
     *      ),
     *      @OA\Response(
     *          response="404",
     *          description="Feature not found"
     *      ),
     *      security={
     *          {"basicAuth":{}, "bearerAuth":{}, "queryAuth":{}}
     *      }
     *  )
     *
     * @param array params
     */
    public function getFeature($params)
    {

        // [IMPORTANT] Default fields output is "_default"
        $feature = new RestoFeature($this->context, $this->user, array(
            'featureId' => $params['featureId'],
            'fields' => $this->context->query['fields'] ?? "_default",
            'collectionName' => $params['collectionName']
        ));

        if (!$feature->isValid()) {
            RestoLogUtil::httpError(404);
        }

        return $feature;
    }
        
    /**
     * Search for features in a given collections
     *
     *  @OA\Get(
     *      path="/collections/{collectionName}/items.{format}",
     *      summary="Get features (search on a specific collection)",
     *      description="List of filters to search features within collection {collectionName}",
     *      tags={"Feature"},
     *      @OA\Parameter(
     *         name="collectionName",
     *         in="path",
     *         required=true,
     *         description="Collection name",
     *         @OA\Schema(
     *             type="string"
     *         )
     *      ),
     *      @OA\Parameter(
     *          name="format",
     *          in="path",
     *          description="Output format - one of *atom* or *json*",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="q",
     *          in="query",
     *          description="Free text search - OpenSearch {searchTerms}",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="limit",
     *          in="query",
     *          description="Number of results returned per page - between 1 and 500 (default 50) - OpenSearcg {count}",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="index",
     *          in="query",
     *          description="First result to provide - minimum 1, (default 1) - OpenSearch {startIndex}",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="page",
     *          in="query",
     *          description="First page to provide - minimum 1, (default 1) - OpenSearch {startPage}",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="lang",
     *          in="query",
     *          description="Two letters language code according to ISO 639-1 (default en) - OpenSearch {language}",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="id",
     *          in="query",
     *          description="Feature identifier (UUID) - OpenSearch {geo:uid}",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="geometry",
     *          in="query",
     *          description="Region of Interest defined in Well Known Text standard (WKT) with coordinates in decimal degrees (EPSG:4326) - OpenSearch {geo:geometry}",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="bbox",
     *          in="query",
     *          description="Region of Interest defined by 'west, south, east, north' coordinates of longitude, latitude, in decimal degrees (EPSG:4326) - OpenSearch {geo:box}",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="name",
     *          in="query",
     *          description="[EXTENSION][egg] Location string e.g. Paris, France  or toponym identifier (i.e. geouid:xxxx) - OpenSearch {geo:name}",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="lon",
     *          in="query",
     *          description="Longitude expressed in decimal degrees (EPSG:4326) - should be used with geo:lat - OpenSearch {geo:lon}",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="lat",
     *          in="query",
     *          description="Latitude expressed in decimal degrees (EPSG:4326) - should be used with geo:lon - OpenSearch {geo:lat}",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="radius",
     *          in="query",
     *          description="Radius expressed in meters - should be used with geo:lon and geo:lat - OpenSearch {geo:radius}",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="startDate",
     *          in="query",
     *          description="Beginning of the time slice of the search query. Format should follow RFC-3339 - OpenSearch {time:start}",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="completionDate",
     *          in="query",
     *          description="End of the time slice of the search query. Format should follow RFC-3339 - OpenSearch {time:end}",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="updated",
     *          in="query",
     *          description="Last update of the product within database - OpenSearch {dc:date}",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="gt",
     *          in="query",
     *          description="Returns features with *sort* key value greater than *gt* value - use this for pagination",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="lt",
     *          in="query",
     *          description="Returns features with *sort* key value lower than *lt* value - use this for pagination",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="pid",
     *          in="query",
     *          description="Like on product identifier",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="sort",
     *          in="query",
     *          description="Sort results by property *id*, *startDate* or *likes* (default: id which corresponds to the publication date). Sorting order is DESCENDING (ASCENDING if property is prefixed by minus sign)",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="owner",
     *          in="query",
     *          description="Limit search to owner's features",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="likes",
     *          in="query",
     *          description="[EXTENSION][social] Limit search to number of likes (interval)",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="liked",
     *          in="query",
     *          description="[EXTENSION][social] Return only liked features from calling user",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="status",
     *          in="query",
     *          description="Feature status (unusued)",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="productType",
     *          in="query",
     *          description="[MODEL][SatelliteModel] A string identifying the entry type (e.g. ER02_SAR_IM__0P, MER_RR__1P, SM_SLC__1S, GES_DISC_AIRH3STD_V005) - OpenSearch {eo:productType}",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="processingLevel",
     *          in="query",
     *          description="[MODEL][SatelliteModel] A string identifying the processing level applied to the entry - OpenSearch {eo:processingLevel}",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="platform",
     *          in="query",
     *          description="[MODEL][SatelliteModel] A string with the platform short name (e.g. Sentinel-1) - OpenSearch {eo:platform}",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="instrument",
     *          in="query",
     *          description="[MODEL][SatelliteModel] A string identifying the instrument (e.g. MERIS, AATSR, ASAR, HRVIR. SAR) - OpenSearch {eo:instrument}",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="sensorType",
     *          in="query",
     *          description="[MODEL][SatelliteModel] A string identifying the sensor type. Suggested values are: OPTICAL, RADAR, ALTIMETRIC, ATMOSPHERIC, LIMB - OpenSearch {eo:sensorType}",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="cloudCover",
     *          in="query",
     *          description="[MODEL][OpticalModel] Cloud cover expressed in percent",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="snowCover",
     *          in="query",
     *          description="[MODEL][OpticalModel] Snow cover expressed in percent",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="waterCover",
     *          in="query",
     *          description="[MODEL][LandCoverModel] Water area expressed in percent",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="urbanCover",
     *          in="query",
     *          description="[MODEL][LandCoverModel] Urban area expressed in percent",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="iceCover",
     *          in="query",
     *          description="[MODEL][LandCoverModel] Ice area expressed in percent",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="herbaceousCover",
     *          in="query",
     *          description="[MODEL][LandCoverModel] Herbaceous area expressed in percent",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="forestCover",
     *          in="query",
     *          description="[MODEL][LandCoverModel] Forest area expressed in percent",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="floodedCover",
     *          in="query",
     *          description="[MODEL][LandCoverModel] Flooded area expressed in percent",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="desertCover",
     *          in="query",
     *          description="[MODEL][LandCoverModel] Desert area expressed in percent",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="cultivatedCover",
     *          in="query",
     *          description="[MODEL][LandCoverModel] Cultivated area expressed in percent",
     *          required=false
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Features collection",
     *          @OA\JsonContent(ref="#/components/schemas/RestoFeatureCollection")
     *      ),
     *      @OA\Response(
     *          response="400",
     *          description="Bad request (i.e. invalid parameter)",
     *          @OA\JsonContent(ref="#/components/schemas/BadRequestError")
     *      ),
     *      @OA\Response(
     *          response="404",
     *          description="Collection not Found",
     *          @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *      )
     * )
     *
     * @param array params
     */
    public function getFeaturesInCollection($params)
    {
        if (isset($params['model'])) {
            return RestoLogUtil::httpError(400, 'You cannot specify a collection and a model at the same time');
        }
        return (new RestoCollection($params['collectionName'], $this->context, $this->user))->load()->search();
    }

    /**
     * Update feature
     *
     *  @OA\Put(
     *      path="/collections/{collectionName}/items/{featureId}",
     *      summary="Update feature property",
     *      description="Update feature {featureId}",
     *      tags={"Feature"},
     *      @OA\Parameter(
     *         name="collectionName",
     *         in="path",
     *         required=true,
     *         description="Collection name",
     *         @OA\Schema(
     *             type="string"
     *         )
     *      ),
     *      @OA\Parameter(
     *         name="featureId",
     *         in="path",
     *         required=true,
     *         description="Feature identifier",
     *         @OA\Schema(
     *             type="string"
     *         )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="The feature is updated",
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="status",
     *                  type="string",
     *                  description="Status is *success*"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string",
     *                  description="Message information"
     *              ),
     *              example={
     *                  "status": "success",
     *                  "message": "Update feature b9eeaf6b-9868-5418-9455-3e77cd349e21"
     *              }
     *          )
     *      ),
     *      @OA\Response(
     *          response="400",
     *          description="Invalide property",
     *          @OA\JsonContent(ref="#/components/schemas/BadRequestError")
     *      ),
     *      @OA\Response(
     *          response="401",
     *          description="Unauthorized",
     *          @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
     *      ),
     *      @OA\Response(
     *          response="403",
     *          description="Forbidden",
     *          @OA\JsonContent(ref="#/components/schemas/ForbiddenError")
     *      ),
     *      @OA\Response(
     *          response="404",
     *          description="Feature not found",
     *          @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *      ),
     *      @OA\RequestBody(
     *         description="Feature description",
     *         @OA\JsonContent(ref="#/components/schemas/InputFeature")
     *      ),
     *      security={
     *          {"basicAuth":{}, "bearerAuth":{}, "queryAuth":{}}
     *      }
     * )
     *
     * @param array $params
     * @param array $body
     */
    public function updateFeature($params, $body)
    {
        $feature = new RestoFeature($this->context, $this->user, array(
            'featureId' => $params['featureId'],
            'collectionName' => $params['collectionName']
        ));

        if (!$feature->isValid()) {
            RestoLogUtil::httpError(404);
        }

        /*
         * Only owner of a feature or admin can update it
         */
        $featureArray = $feature->toArray();
        if (! isset($featureArray['properties']['owner']) || $featureArray['properties']['owner'] !== $this->user->profile['id']) {
            if (! $this->user->hasGroup(Resto::GROUP_ADMIN_ID)) {
                RestoLogUtil::httpError(403);
            }
        }

        // Load collection
        $collection = (new RestoCollection($feature->collectionName, $this->context, $this->user))->load();

        return $collection->model->updateFeature($feature, $collection, $body);
    }

    /**
     * Update feature property
     *
     *  @OA\Put(
     *      path="/collections/{collectionName}/items/{featureId}/{property}",
     *      summary="Update feature property",
     *      description="Update {property} for feature {featureId}",
     *      tags={"Feature"},
     *      @OA\Parameter(
     *         name="collectionName",
     *         in="path",
     *         required=true,
     *         description="Collection name",
     *         @OA\Schema(
     *             type="string"
     *         )
     *      ),
     *      @OA\Parameter(
     *         name="featureId",
     *         in="path",
     *         required=true,
     *         description="Feature identifier",
     *         @OA\Schema(
     *             type="string"
     *         )
     *      ),
     *      @OA\Parameter(
     *         name="property",
     *         in="path",
     *         required=true,
     *         description="Property to update",
     *         @OA\Schema(
     *             type="string"
     *         )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="The property is updated",
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="status",
     *                  type="string",
     *                  description="Status is *success*"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string",
     *                  description="Message information"
     *              ),
     *              example={
     *                  "status": "success",
     *                  "message": "Update property for feature b9eeaf6b-9868-5418-9455-3e77cd349e21"
     *              }
     *          )
     *      ),
     *      @OA\Response(
     *          response="400",
     *          description="Invalide property",
     *          @OA\JsonContent(ref="#/components/schemas/BadRequestError")
     *      ),
     *      @OA\Response(
     *          response="401",
     *          description="Unauthorized",
     *          @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
     *      ),
     *      @OA\Response(
     *          response="403",
     *          description="Forbidden",
     *          @OA\JsonContent(ref="#/components/schemas/ForbiddenError")
     *      ),
     *      @OA\Response(
     *          response="404",
     *          description="Feature not found",
     *          @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *      ),
     *      @OA\RequestBody(
     *         description="Property value to update",
     *         @OA\JsonContent(
     *              @OA\Property(
     *                  property="value",
     *                  description="New property value"
     *              ),
     *              example={
     *                  "value":1
     *              }
     *          )
     *      ),
     *      security={
     *          {"basicAuth":{}, "bearerAuth":{}, "queryAuth":{}}
     *      }
     * )
     *
     * @param array $params
     * @param array $body
     */
    public function updateFeatureProperty($params, $body)
    {
        $feature = new RestoFeature($this->context, $this->user, array(
            'featureId' => $params['featureId'],
            'collectionName' => $params['collectionName']
        ));

        if (!$feature->isValid()) {
            RestoLogUtil::httpError(404);
        }

        // Only admin or owner can change feature properties
        if ($this->user->profile['id'] !== $feature->toArray()['properties']['owner']) {
            if (! $this->user->hasGroup(Resto::GROUP_ADMIN_ID)) {
                RestoLogUtil::httpError(403);
            }
        }
        
        // A value is mandatory
        if (! isset($body['value'])) {
            return RestoLogUtil::httpError(400, 'Missing mandatory "value" property');
        }

        // Only these properties can be updated
        if (! in_array($params['property'], array('title', 'description', 'visibility', 'owner', 'status', 'quicklook', 'thumbnail'))) {
            return RestoLogUtil::httpError(400, 'Invalid property "' . $params['property'] . '"');
        }
        
        // Only admin can change owner property
        if ($params['property'] === 'owner' && ! $this->user->hasGroup(Resto::GROUP_ADMIN_ID)) {
            RestoLogUtil::httpError(403);
        }

        return (new FeaturesFunctions($this->context->dbDriver))->updateFeatureProperty(
            $feature,
            $params['property'],
            $body['value']
        );
    }

    /**
     * Delete feature
     *
     * @OA\Delete(
     *      tags={"Feature"},
     *      path="/collections/{collectionName}/items/{featureId}",
     *      summary="Delete feature",
     *      description="Delete feature {featureId}",
     *      @OA\Parameter(
     *         name="collectionName",
     *         in="path",
     *         required=true,
     *         description="Collection name",
     *         @OA\Schema(
     *             type="string"
     *         )
     *      ),
     *      @OA\Parameter(
     *          name="featureId",
     *          in="path",
     *          description="Feature identifier",
     *          required=true,
     *          @OA\Schema(
     *             type="string"
     *         )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="The feature is delete",
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="status",
     *                  type="string",
     *                  description="Status is *success*"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string",
     *                  description="Message information"
     *              ),
     *              example={
     *                  "status": "success",
     *                  "message": "Feature 7e5caa78-5127-53e5-97ff-ddf44984ef56 deleted"
     *              }
     *          )
     *      ),
     *      @OA\Response(
     *          response="400",
     *          description="Missing mandatory feature identifier",
     *          @OA\JsonContent(ref="#/components/schemas/BadRequestError")
     *      ),
     *      @OA\Response(
     *          response="401",
     *          description="Unauthorized",
     *          @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
     *      ),
     *      @OA\Response(
     *          response="403",
     *          description="Only user with *update* rights can delete a feature",
     *          @OA\JsonContent(ref="#/components/schemas/ForbiddenError")
     *      ),
     *      @OA\Response(
     *          response="404",
     *          description="Feature not found",
     *          @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *      ),
     *      security={
     *          {"basicAuth":{}, "bearerAuth":{}, "queryAuth":{}}
     *      }
     *  )
     * @param array $params
     */
    public function deleteFeature($params)
    {
        $feature = new RestoFeature($this->context, $this->user, array(
            'featureId' => $params['featureId'],
            'collectionName' => $params['collectionName']
        ));

        if (!$feature->isValid()) {
            RestoLogUtil::httpError(404);
        }

        /*
         * Only owner of a feature or admin can delete it
         */
        $featureArray = $feature->toArray();
        if (!isset($featureArray['properties']['owner']) || $featureArray['properties']['owner'] !== $this->user->profile['id']) {
            if (! $this->user->hasGroup(Resto::GROUP_ADMIN_ID)) {
                RestoLogUtil::httpError(403);
            }
        }
        
        // Result contains boolean for facetsDeleted
        $result = $feature->removeFromStore();

        return RestoLogUtil::success('Feature deleted', array(
            'featureId' => $feature->id,
            'facetsDeleted' => $result['facetsDeleted']
        ));
    }

}
