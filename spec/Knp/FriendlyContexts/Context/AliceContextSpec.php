<?php

namespace spec\Knp\FriendlyContexts\Context;

use Nelmio\Alice\ObjectBag;
use Nelmio\Alice\ObjectSet;
use Nelmio\Alice\ParameterBag;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class AliceContextSpec extends ObjectBehavior
{
    /**
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     * @param \Doctrine\Common\Persistence\ManagerRegistry $doctrine
     * @param \Doctrine\Common\Persistence\ObjectManager $manager
     * @param \Behat\Behat\Hook\Scope\ScenarioScope $event
     * @param Knp\FriendlyContexts\Alice\Fixtures\Loader $loader
     * @param \Behat\Gherkin\Node\FeatureNode $feature
     * @param \Behat\Gherkin\Node\ScenarioNode $scenario
     * @param \Doctrine\Common\Persistence\Mapping\ClassMetadataFactory $metadataFactory
     * @param \Doctrine\Common\Persistence\Mapping\ClassMetadata $userMetadata
     * @param \Doctrine\Common\Persistence\Mapping\ClassMetadata $placeMetadata
     * @param \Doctrine\Common\Persistence\Mapping\ClassMetadata $productMetadata
     */
    function let($container, $doctrine, $manager, $event, $loader, $feature, $scenario, $metadataFactory, $userMetadata, $placeMetadata, $productMetadata)
    {
        $doctrine->getManager()->willReturn($manager);
        $feature->getTags()->willReturn([ 'alice(Place)', 'admin' ]);
        $scenario->getTags()->willReturn([ 'alice(User)' ]);
        $event->getFeature()->willReturn($feature);
        $event->getScenario()->willReturn($scenario);
        $objectSet = new ObjectSet(new ParameterBag(), new ObjectBag());
        $loader->loadFiles(['user.yml', 'place.yml'])->willReturn($objectSet);
        $loader->loadFiles(['product.yml'])->willReturn($objectSet);
        $loader->loadFiles(['user.yml', 'product.yml', 'place.yml'])->willReturn($objectSet);
        $loader->getCache()->willReturn([]);
        $loader->clearCache()->willReturn(null);
        $fixtures = [ 'User' => 'user.yml', 'Product' => 'product.yml', 'Place' => 'place.yml' ];
        $config = [ 'alice' => [ 'fixtures' => $fixtures, 'dependencies' => [] ]];
        $container->has(Argument::any())->willReturn(true);
        $container->hasParameter(Argument::any())->willReturn(true);
        $container->get('friendly.alice.fixtures.loader')->willReturn($loader);
        $container->get('doctrine')->willReturn($doctrine);
        $container->getParameter('friendly.alice.fixtures')->willReturn($fixtures);
        $container->getParameter('friendly.alice.dependencies')->willReturn([]);
        $manager->getMetadataFactory()->willReturn($metadataFactory);
        $metadataFactory->getAllMetadata()->willReturn([$userMetadata, $placeMetadata, $productMetadata]);
        $userMetadata->getName()->willReturn('User');
        $placeMetadata->getName()->willReturn('Place');
        $productMetadata->getName()->willReturn('Product');

        $this->initialize($config, $container);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('Knp\FriendlyContexts\Context\AliceContext');
    }

    function it_should_load_specific_fixtures($event, $loader, $manager)
    {
        $manager->flush()->shouldBeCalled();

        $loader->loadFiles(['user.yml', 'place.yml'])->shouldBeCalled();
        $loader->loadFiles(['user.yml'])->shouldNotBeCalled();
        $loader->loadFiles(['product.yml'])->shouldNotBeCalled();
        $loader->loadFiles(['place.yml'])->shouldNotBeCalled();

        $this->loadAlice($event);
    }

    function it_should_load_all_fixtures($loader, $event, $scenario, $manager)
    {
        $scenario->getTags()->willReturn([ 'alice(*)' ]);
        $manager->flush()->shouldBeCalled();

        $loader->loadFiles(['user.yml', 'product.yml', 'place.yml'])->shouldBeCalled();
        $loader->loadFiles(['user.yml'])->shouldNotBeCalled();
        $loader->loadFiles(['product.yml'])->shouldNotBeCalled();
        $loader->loadFiles(['place.yml'])->shouldNotBeCalled();

        $this->loadAlice($event);
    }

    function it_should_resolve_deps($container, $loader, $event, $scenario, $manager)
    {
        $scenario->getTags()->willReturn([]);
        $manager->flush()->shouldBeCalled();

        $loader->loadFiles(['user.yml', 'place.yml'])->shouldBeCalled();
        $loader->loadFiles(['user.yml'])->shouldNotBeCalled();
        $loader->loadFiles(['product.yml'])->shouldNotBeCalled();
        $loader->loadFiles(['place.yml'])->shouldNotBeCalled();

        $deps = [ 'Place' => [ 'User' ] ];
        $container->getParameter('friendly.alice.dependencies')->willReturn($deps);

        $this->loadAlice($event);
    }

    function it_should_not_loop_infinitly($container, $loader, $event, $scenario, $manager)
    {
        $scenario->getTags()->willReturn([]);
        $manager->flush()->shouldBeCalled();

        $loader->loadFiles(['user.yml', 'place.yml'])->shouldBeCalled();
        $loader->loadFiles(['user.yml'])->shouldNotBeCalled();
        $loader->loadFiles(['product.yml'])->shouldNotBeCalled();
        $loader->loadFiles(['place.yml'])->shouldNotBeCalled();

        $deps = [ 'Place' => [ 'User' ], 'User' => [ 'Place' ] ];
        $container->getParameter('friendly.alice.dependencies')->willReturn($deps);

        $this->loadAlice($event);
    }
}
