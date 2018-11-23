<?php

namespace Rezzza\DoctrineSchemaMultiMapping\Domain;

use Doctrine\ORM\Mapping\ClassMetadata;

class MetadataMerger
{
    public function merge(ClassMetadata $reference, ClassMetadata $redundancy)
    {
        $notSupported = array(
            'customGeneratorDefinition',
            'customRepositoryClassName',
            'identifier',
            'inheritanceType',
            'generatorType',
            'isIdentifierComposite',
            'containsForeignIdentifier',
            'idGenerator',
            'sequenceGeneratorDefinition',
            'tableGeneratorDefinition',
            'changeTrackingPolicy',
        );

        foreach ($notSupported as $property) {
            if ($reference->$property != $redundancy->$property) {

                if (preg_match('/MVB/', $redundancy->name)) {
                    continue;
                }

                throw new Exception\UnsupportedException(sprintf('"%s" mapping property changed on table "%s", not supported', $property, $redundancy->getTableName()));
            }
        }

        $this->guardFieldMappingsConsistency($reference, $redundancy);
        $reference->fieldMappings       = array_merge($reference->fieldMappings, $redundancy->fieldMappings);

        $this->guardFieldNamesConsistency($reference, $redundancy);
        $reference->fieldNames          = array_merge($reference->fieldNames, $redundancy->fieldNames);

        // @todo check for validating mergeability of association mappings.
        $reference->associationMappings = array_merge($reference->associationMappings, $redundancy->associationMappings);
    }

    private function guardFieldMappingsConsistency(ClassMetadata $reference, ClassMetadata $redundancy)
    {
        $referenceMappings  = $reference->fieldMappings;
        $redundancyMappings = $redundancy->fieldMappings;
        $redundancyMappings = array_map('array_filter', $redundancyMappings);

        foreach (array_intersect_key($referenceMappings, $redundancyMappings) as $key => $val) {

            // theses Mappings have to be removed since references will change.
            unset($referenceMappings[$key]['declared'], $referenceMappings[$key]['inherited'], $referenceMappings[$key]['originalClass']);
            unset($redundancyMappings[$key]['declared'], $redundancyMappings[$key]['inherited'], $redundancyMappings[$key]['originalClass']);

            if ($referenceMappings[$key] != $redundancyMappings[$key]) {

                if (count($referenceMappings[$key]) > count($redundancyMappings[$key])) {
                    $redundancyMappings[$key] = array_merge($redundancyMappings[$key], array_diff($referenceMappings[$key], $redundancyMappings[$key]));
                } elseif (count($redundancyMappings[$key]) > count($referenceMappings[$key])) {
                    $referenceMappings[$key] = array_merge($referenceMappings[$key], array_diff($redundancyMappings[$key], $referenceMappings[$key]));
                }

                if ($referenceMappings[$key] != $redundancyMappings[$key]) {
                    throw new Exception\LogicException(sprintf('Field mapping "%s" changed on table "%s"', $key, $redundancy->getTableName()));
                }
            }
        }
    }

    private function guardFieldNamesConsistency(ClassMetadata $reference, ClassMetadata $redundancy)
    {
        $referenceFields  = $reference->columnNames;
        $redundancyFields = $redundancy->columnNames;

        foreach (array_intersect_key($referenceFields, $redundancyFields) as $key => $val) {
            if ($referenceFields[$key] != $redundancyFields[$key]) {
                throw new Exception\LogicException(sprintf('Field name "%s" changed on table "%s"', $key, $redundancy->getTableName()));
            }
        }
    }
}
