<?php

namespace ViliamHusar\C4ml\GraphvizGenerator;

use ViliamHusar\C4ml\Model\Container;
use ViliamHusar\C4ml\Model\ExternalSystem;
use ViliamHusar\C4ml\Model\ExternalUser;
use ViliamHusar\C4ml\Model\InternalSystem;
use ViliamHusar\C4ml\Model\InternalUser;
use ViliamHusar\C4ml\Model\Model;
use phpDocumentor\GraphViz\Edge;
use phpDocumentor\GraphViz\Graph;
use phpDocumentor\GraphViz\Node;
use ViliamHusar\C4ml\Model\Usage;

class GraphvizGenerator
{
    const MODE_ALL = 'all';
    const MODE_SELECTIVE = 'selective';

    protected $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * @param Model $model
     * @param string $mode
     * @param array $internalSystemIds
     *
     * @return Graph
     */
    public function generate(Model $model, $mode = self::MODE_ALL, array $internalSystemIds = [])
    {
        $elementsNodes = [];
        $requiredElementsIds = [];
        $connections = [];

        $graph = Graph::create('model');

        // add internal systems with container view
        foreach ($model->getInternalSystems() as $internalSystem) {
            if (self::MODE_SELECTIVE === $mode && !in_array($internalSystem->getId(), $internalSystemIds)) {
                continue;
            }

            $internalSystemGraph = $this->createInternalSystemGraph($internalSystem);
            $graph->addGraph($internalSystemGraph);

            foreach ($internalSystem->getContainers() as $container) {
                $containerNode = $this->createContainerNode($container);
                $elementsNodes[$container->getId()] = $containerNode;
                $internalSystemGraph->setNode($containerNode);

                foreach ($container->getUsages() as $usage) {
                    $connections[$container->getId()][$usage->getTarget()->getId()][] = $usage;
                }
            }
        }

        // collect connections from external systems
        foreach ($model->getExternalSystems() as $externalSystem) {
            foreach ($externalSystem->getUsages() as $usage) {
                if (in_array($usage->getTarget()->getId(), array_keys($elementsNodes))) {
                    $connections[$externalSystem->getId()][$usage->getTarget()->getId()][] = $usage;
                }
            }
        }

        // collect connections from internal systems
        foreach ($model->getInternalSystems() as $internalSystem) {
            foreach ($internalSystem->getContainers() as $container) {
                if (in_array($container->getId(), array_keys($elementsNodes))) {
                    continue;
                }

                foreach ($container->getUsages() as $usage) {
                    if (in_array($usage->getTarget()->getId(), array_keys($elementsNodes))) {
                        $connections[$container->getId()][$usage->getTarget()->getId()][] = $usage;
                    }
                }
            }
        }

        // collect connections from external users
        foreach ($model->getExternalUsers() as $externalUser) {
            foreach ($externalUser->getUsages() as $usage) {
                if (in_array($usage->getTarget()->getId(), array_keys($elementsNodes))) {
                    $connections[$externalUser->getId()][$usage->getTarget()->getId()][] = $usage;
                }
            }
        }

        // collect connections from internal users
        foreach ($model->getInternalUsers() as $internalUser) {
            foreach ($internalUser->getUsages() as $usage) {
                if (in_array($usage->getTarget()->getId(), array_keys($elementsNodes))) {
                    $connections[$internalUser->getId()][$usage->getTarget()->getId()][] = $usage;
                }
            }
        }

        // summarise required elements
        foreach ($connections as $elementId => $usages) {
            $requiredElementsIds = array_merge($requiredElementsIds, [$elementId], array_keys($usages));
        }

        // add nodes for internal users
        foreach ($model->getInternalUsers() as $internalUser) {
            if (in_array($internalUser->getId(), $requiredElementsIds)) {
                $internalUserNode = $this->createInternalUserNode($internalUser);
                $graph->setNode($internalUserNode);
                $elementsNodes[$internalUser->getId()] = $internalUserNode;
            }
        }

        // add nodes for external users
        foreach ($model->getExternalUsers() as $externalUser) {
            if (in_array($externalUser->getId(), $requiredElementsIds)) {
                $externalUserNode = $this->createExternalUserNode($externalUser);
                $graph->setNode($externalUserNode);
                $elementsNodes[$externalUser->getId()] = $externalUserNode;
            }
        }

        // add nodes for external systems
        foreach ($model->getExternalSystems() as $externalSystem) {
            if (in_array($externalSystem->getId(), $requiredElementsIds)) {
                $externalSystemNode = $this->createExternalSystemNode($externalSystem);
                $graph->setNode($externalSystemNode);
                $elementsNodes[$externalSystem->getId()] = $externalSystemNode;
            }
        }

        // add nodes for internal systems
        foreach ($model->getInternalSystems() as $internalSystem) {
            $internalSystemNode = null;

            foreach ($internalSystem->getContainers() as $container) {
                if (in_array($container->getId(), array_keys($elementsNodes))) {
                    continue;
                }

                if (in_array($container->getId(), $requiredElementsIds)) {
                    if (null === $internalSystemNode) {
                        $internalSystemNode = $this->createInternalSystemNode($internalSystem);
                        $graph->setNode($internalSystemNode);
                    }

                    $elementsNodes[$container->getId()] = $internalSystemNode;
                }
            }
        }

        // create edges
        foreach ($connections as $sourceElementId => $targetElements) {
            foreach ($targetElements as $targetElementId => $usages) {
                $edge = $this->createConnectionEdge($elementsNodes[$sourceElementId], $elementsNodes[$targetElementId], $usages);

                $graph->link($edge);
            }
        }

        return $graph;
    }

    protected function createInternalSystemGraph(InternalSystem $internalSystem)
    {
        $description = wordwrap($internalSystem->getDescription(), 20, "<br />\n");

        $label = <<<LABEL
            <
                <table border="0" cellborder="0" cellspacing="0">
                  <tr><td><font point-size="12">{$internalSystem->getName()}</font></td></tr>
                  <tr><td><font point-size="8">[Internal System]</font></td></tr>
                  <tr><td>$description</td></tr>
                </table>
            >
LABEL;

        $internalSystemGraph = Graph::create('cluster__' . $internalSystem->getId());
        $internalSystemGraph
            ->setLabel(trim($label))
            ->setStyle('rounded')
            ->setFontsize(10)
            ->setFontcolor('#263238')
            ->setFontname('helvetica')
            ->setShape('box')
            ->setColor('#263238');

        return $internalSystemGraph;
    }

    protected function createExternalSystemNode(ExternalSystem $externalSystem)
    {
        $description = wordwrap($externalSystem->getDescription(), 20, "<br />\n");

        $label = <<<LABEL
                <
                    <table border="0" cellborder="0" cellspacing="0">
                      <tr><td><font point-size="12">{$externalSystem->getName()}</font></td></tr>
                      <tr><td><font point-size="8">[External System]</font></td></tr>
                      <tr><td>$description</td></tr>
                    </table>
                >
LABEL;

        $externalSystemNode = Node::create($externalSystem->getId(), trim($label));
        $externalSystemNode
            ->setStyle('rounded,dashed')
            ->setFontsize(10)
            ->setFontcolor('#263238')
            ->setFontname('helvetica')
            ->setShape('box')
            ->setColor('#263238');

        return $externalSystemNode;
    }

    protected function createInternalSystemNode(InternalSystem $internalSystem)
    {
        $description = wordwrap($internalSystem->getDescription(), 20, "<br />\n");

        $label = <<<LABEL
                <
                    <table border="0" cellborder="0" cellspacing="0">
                      <tr><td><font point-size="12">{$internalSystem->getName()}</font></td></tr>
                      <tr><td><font point-size="8">[Internal System]</font></td></tr>
                      <tr><td>$description</td></tr>
                    </table>
                >
LABEL;

        $internalSystemNode = Node::create($internalSystem->getId(), trim($label));
        $internalSystemNode
            ->setStyle('rounded')
            ->setFontsize(10)
            ->setFontcolor('#263238')
            ->setFontname('helvetica')
            ->setShape('box')
            ->setColor('#263238');

        return $internalSystemNode;
    }

    protected function createContainerNode(Container $container)
    {
        $description = wordwrap($container->getDescription(), 20, "<br />\n");

        if (!empty($container->getType())) {
            $elementType = sprintf("[Container: %s]", $container->getType());
        } else {
            $elementType = '[Container]';
        }

        $label = <<<LABEL
            <
                <table border="0" cellborder="0" cellspacing="0">
                    <tr><td><font point-size="12">{$container->getName()}</font></td></tr>
                    <tr><td><font point-size="8">$elementType</font></td></tr>
                    <tr><td>$description</td></tr>
                </table>
            >
LABEL;

        $containerNode = Node::create($container->getId(), trim($label));
        $containerNode
            ->setStyle('rounded,filled')
            ->setFontsize(10)
            ->setFontcolor('#263238')
            ->setFontname('helvetica')
            ->setShape('box')
            ->setColor('#263238')
            ->setFillcolor('#ECEFF1:#90A4AE')
            ->setGradientangle(270);

        return $containerNode;
    }

    protected function createInternalUserNode(InternalUser $internalUser)
    {
        $description = wordwrap($internalUser->getDescription(), 20, "<br />\n");

        $label = <<<LABEL
            <
                <table border="0" cellborder="0" cellspacing="0">
                    <tr><td><font point-size="12">{$internalUser->getName()}</font></td></tr>
                    <tr><td><font point-size="8">[Internal User]</font></td></tr>
                    <tr><td>$description</td></tr>
                </table>
            >
LABEL;

        $internalUserNode = Node::create($internalUser->getId(), trim($label));
        $internalUserNode
            ->setFontsize(10)
            ->setFontcolor('#263238')
            ->setFontname('helvetica')
            ->setShape('underline')
            ->setColor('#263238');

        return $internalUserNode;
    }

    protected function createExternalUserNode(ExternalUser $externalUser)
    {
        $description = wordwrap($externalUser->getDescription(), 20, "<br />\n");

        $label = <<<LABEL
            <
                <table border="0" cellborder="0" cellspacing="0">
                    <tr><td><font point-size="12">{$externalUser->getName()}</font></td></tr>
                    <tr><td><font point-size="8">[External user]</font></td></tr>
                    <tr><td>$description</td></tr>
                </table>
            >
LABEL;

        $externalUserNode = Node::create($externalUser->getId(), trim($label));
        $externalUserNode
            ->setStyle('dashed')
            ->setFontsize(10)
            ->setFontcolor('#263238')
            ->setFontname('helvetica')
            ->setShape('underline')
            ->setColor('#263238');

        return $externalUserNode;
    }

    /**
     * @param Node $sourceNode
     * @param Node $targetNode
     * @param Usage[] $usages
     *
     * @return Edge
     */
    protected function createConnectionEdge(Node $sourceNode, Node $targetNode, array $usages = [])
    {
        $labels = [];

        foreach ($usages as $usage) {
            $for = wordwrap($usage->getFor(), 20, "<br />\n");
            if (empty($usage->getType())) {
                $label = <<<LABEL
                    <tr><td>$for</td></tr>
LABEL;
            } else {
                $label = <<<LABEL
                    <tr><td>$for</td></tr>
                    <tr><td><font point-size="8">[{$usage->getType()}]</font></td></tr>
LABEL;
            }

            $labels[] = $label;
        }

        $joinedLabels = implode('', $labels);


        $label = <<<LABEL
            <
                <table border="0" cellborder="0" cellspacing="0">
                $joinedLabels
                </table>
            >
LABEL;

        $edge = Edge::create($sourceNode, $targetNode);
        $edge
            ->setLabel(trim($label))
            ->setFontsize(10)
            ->setFontcolor('#263238')
            ->setFontname('helvetica')
            ->setColor('#263238');

        return $edge;
    }

}