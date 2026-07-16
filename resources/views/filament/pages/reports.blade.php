<x-filament-panels::page>
    {{ $this->filtersForm }}

    <x-filament-widgets::widgets
        :columns="$this->getColumns()"
        :data="['filters' => $this->filters]"
        :widgets="$this->getWidgets()"
    />
</x-filament-panels::page>
