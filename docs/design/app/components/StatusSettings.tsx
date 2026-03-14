import {
  CheckCircle2,
  Clock,
  BookOpen,
  XCircle,
  Check,
  ThumbsUp,
  ThumbsDown,
  Star,
  Send,
  ArrowRightLeft,
  Ban,
  AlertCircle,
  Circle,
  Settings,
  Save
} from 'lucide-react';
import { Button } from './ui/button';
import { Card } from './ui/card';
import { Input } from './ui/input';
import { Label } from './ui/label';
import { useState } from 'react';

const iconOptions = [
  { icon: CheckCircle2, name: 'CheckCircle' },
  { icon: ThumbsUp, name: 'ThumbsUp' },
  { icon: Check, name: 'Check' },
  { icon: Star, name: 'Star' },
  { icon: BookOpen, name: 'BookOpen' },
  { icon: Send, name: 'Send' },
  { icon: ArrowRightLeft, name: 'Exchange' },
  { icon: XCircle, name: 'XCircle' },
  { icon: ThumbsDown, name: 'ThumbsDown' },
  { icon: Ban, name: 'Ban' },
  { icon: AlertCircle, name: 'AlertCircle' },
  { icon: Clock, name: 'Clock' },
  { icon: Circle, name: 'Circle' },
];

interface StatusConfig {
  name: string;
  color: string;
  iconIndex: number;
}

export function StatusSettings() {
  const [statuses, setStatuses] = useState<Record<string, StatusConfig>>({
    approved: {
      name: 'Approved - Order',
      color: '#16a34a',
      iconIndex: 0, // CheckCircle2
    },
    ill: {
      name: 'Send to ILL',
      color: '#2563eb',
      iconIndex: 4, // BookOpen
    },
    denied: {
      name: 'Deny Request',
      color: '#dc2626',
      iconIndex: 7, // XCircle
    },
    pending: {
      name: 'Keep Pending',
      color: '#f59e0b',
      iconIndex: 11, // Clock
    },
  });

  const updateStatus = (key: string, field: keyof StatusConfig, value: string | number) => {
    setStatuses({
      ...statuses,
      [key]: {
        ...statuses[key],
        [field]: value,
      },
    });
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <header className="bg-white border-b border-gray-200 px-6 py-4">
        <div className="max-w-5xl mx-auto">
          <div className="flex items-center gap-3">
            <Settings className="h-6 w-6 text-gray-600" />
            <h1 className="text-2xl font-semibold text-gray-900">Status Settings</h1>
          </div>
          <p className="text-sm text-gray-600 mt-1">Configure status names, colors, and icons for request workflows</p>
        </div>
      </header>

      <main className="max-w-5xl mx-auto px-6 py-8">
        <Card className="p-6 bg-white shadow-sm">
          <div className="space-y-8">
            {Object.entries(statuses).map(([key, status]) => {
              const SelectedIcon = iconOptions[status.iconIndex].icon;
              
              return (
                <div key={key} className="pb-8 border-b border-gray-200 last:border-b-0 last:pb-0">
                  <div className="flex items-center gap-3 mb-4">
                    <div 
                      className="w-10 h-10 rounded-lg flex items-center justify-center"
                      style={{ backgroundColor: status.color }}
                    >
                      <SelectedIcon className="h-5 w-5 text-white" />
                    </div>
                    <div>
                      <h3 className="font-medium text-gray-900 capitalize">{key} Status</h3>
                      <p className="text-xs text-gray-500">Customize appearance and behavior</p>
                    </div>
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {/* Status Name */}
                    <div>
                      <Label htmlFor={`${key}-name`} className="text-sm font-medium text-gray-700 mb-2 block">
                        Status Name
                      </Label>
                      <Input
                        id={`${key}-name`}
                        value={status.name}
                        onChange={(e) => updateStatus(key, 'name', e.target.value)}
                        placeholder="Enter status name"
                      />
                    </div>

                    {/* Color Picker */}
                    <div>
                      <Label htmlFor={`${key}-color`} className="text-sm font-medium text-gray-700 mb-2 block">
                        Color
                      </Label>
                      <div className="flex gap-2">
                        <Input
                          id={`${key}-color`}
                          type="color"
                          value={status.color}
                          onChange={(e) => updateStatus(key, 'color', e.target.value)}
                          className="w-20 h-10 p-1 cursor-pointer"
                        />
                        <Input
                          type="text"
                          value={status.color}
                          onChange={(e) => updateStatus(key, 'color', e.target.value)}
                          placeholder="#000000"
                          className="flex-1 font-mono text-sm"
                        />
                      </div>
                    </div>

                    {/* Icon Selector */}
                    <div>
                      <Label className="text-sm font-medium text-gray-700 mb-2 block">
                        Icon
                      </Label>
                      <div className="grid grid-cols-7 gap-1 p-2 bg-gray-50 rounded-lg border border-gray-200">
                        {iconOptions.map((option, index) => {
                          const Icon = option.icon;
                          return (
                            <button
                              key={index}
                              onClick={() => updateStatus(key, 'iconIndex', index)}
                              className={`w-8 h-8 rounded flex items-center justify-center transition-colors ${
                                status.iconIndex === index
                                  ? 'text-white'
                                  : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-100'
                              }`}
                              style={status.iconIndex === index ? { backgroundColor: status.color } : {}}
                              title={option.name}
                            >
                              <Icon className="h-4 w-4" />
                            </button>
                          );
                        })}
                      </div>
                    </div>
                  </div>

                  {/* Preview */}
                  <div className="mt-4 pt-4 border-t border-gray-100">
                    <Label className="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2 block">
                      Preview
                    </Label>
                    <Button
                      className="h-11 font-medium text-white"
                      style={{ backgroundColor: status.color }}
                    >
                      <SelectedIcon className="h-4 w-4 mr-2" />
                      {status.name}
                    </Button>
                  </div>
                </div>
              );
            })}
          </div>

          <div className="mt-8 pt-6 border-t border-gray-200 flex justify-end gap-3">
            <Button variant="outline">
              Cancel
            </Button>
            <Button className="bg-indigo-600 hover:bg-indigo-700">
              <Save className="h-4 w-4 mr-2" />
              Save Changes
            </Button>
          </div>
        </Card>
      </main>
    </div>
  );
}
