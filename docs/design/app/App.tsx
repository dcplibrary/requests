import { 
  ArrowLeft, 
  BookOpen,
  CheckCircle2,
  Clock,
  ExternalLink, 
  HelpCircle, 
  Mail,
  Phone,
  Search,
  ShoppingCart,
  Trash2, 
  User,
  XCircle,
  BarChart3,
  ChevronDown,
  ChevronUp,
  FileText,
  ArrowRightLeft,
  MessageSquare,
  Settings,
  Check,
  Circle,
  Square,
  Star,
  Heart,
  AlertCircle,
  Ban,
  ThumbsUp,
  ThumbsDown,
  Send
} from 'lucide-react';
import { Button } from './components/ui/button';
import { Card } from './components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from './components/ui/select';
import { Textarea } from './components/ui/textarea';
import { Badge } from './components/ui/badge';
import { Tabs, TabsList, TabsTrigger } from './components/ui/tabs';
import { useState } from 'react';
import logoImage from 'figma:asset/aab1f82c8b1122bc21c8b6570e867fde188450d5.png';

const iconOptions = {
  approve: [
    { icon: CheckCircle2, name: 'CheckCircle' },
    { icon: ThumbsUp, name: 'ThumbsUp' },
    { icon: Check, name: 'Check' },
    { icon: Star, name: 'Star' },
  ],
  ill: [
    { icon: BookOpen, name: 'BookOpen' },
    { icon: Send, name: 'Send' },
    { icon: ArrowRightLeft, name: 'Exchange' },
  ],
  deny: [
    { icon: XCircle, name: 'XCircle' },
    { icon: ThumbsDown, name: 'ThumbsDown' },
    { icon: Ban, name: 'Ban' },
    { icon: AlertCircle, name: 'AlertCircle' },
  ],
  pending: [
    { icon: Clock, name: 'Clock' },
    { icon: Circle, name: 'Circle' },
    { icon: AlertCircle, name: 'Alert' },
  ],
};

export default function App() {
  const [showHistory, setShowHistory] = useState(false);
  const [showPatronDetails, setShowPatronDetails] = useState(false);
  const [showNote, setShowNote] = useState(false);
  const [showIconSelector, setShowIconSelector] = useState(false);
  const [selectedIcons, setSelectedIcons] = useState({
    approve: 0,
    ill: 0,
    deny: 0,
    pending: 0,
  });

  const ApproveIcon = iconOptions.approve[selectedIcons.approve].icon;
  const IllIcon = iconOptions.ill[selectedIcons.ill].icon;
  const DenyIcon = iconOptions.deny[selectedIcons.deny].icon;
  const PendingIcon = iconOptions.pending[selectedIcons.pending].icon;

  return (
    <div className="min-h-screen bg-gray-50">
      <header className="bg-white border-b border-gray-200 px-6">
        <div className="max-w-7xl mx-auto">
          <div className="flex items-end justify-between">
            <div className="flex items-end gap-12">
              <div className="flex items-center gap-3 pb-4 pt-4">
                <img 
                  src={logoImage} 
                  alt="Daviess County Public Library" 
                  className="h-8"
                />
                <span className="font-semibold text-gray-900 text-lg">APP_NAME</span>
              </div>
              
              <div className="flex items-center gap-8">
                <button className="text-blue-600 font-medium hover:text-blue-700 pb-4 border-b-2 border-blue-600">
                  Suggestions for Purchases
                </button>
                <button className="text-gray-600 font-medium hover:text-gray-900 pb-4 border-b-2 border-transparent hover:border-gray-300">
                  Interlibrary Loan
                </button>
                <button className="text-gray-600 font-medium hover:text-gray-900 pb-4 border-b-2 border-transparent hover:border-gray-300">
                  Titles
                </button>
                <button className="text-gray-600 font-medium hover:text-gray-900 pb-4 border-b-2 border-transparent hover:border-gray-300">
                  Patrons
                </button>
                <button className="text-gray-600 font-medium hover:text-gray-900 pb-4 border-b-2 border-transparent hover:border-gray-300">
                  Settings
                </button>
              </div>
            </div>
            
            <div className="flex items-center gap-3 pb-4 pt-4">
              <Button variant="ghost" size="sm">
                <HelpCircle className="h-4 w-4" />
              </Button>
              <div className="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center">
                <span className="text-white text-sm font-medium">BL</span>
              </div>
            </div>
          </div>
        </div>
      </header>

      <main className="max-w-7xl mx-auto px-6 py-2">
        <Button variant="ghost" className="mb-2 -ml-2 text-blue-600 hover:text-blue-700">
          <ArrowLeft className="h-4 w-4 mr-1" />
          Back to requests
        </Button>

        <div className="bg-gray-100 rounded-lg px-4 py-3 mb-6 flex items-center gap-6 text-sm">
          <div className="flex items-center gap-2">
            <Clock className="h-4 w-4 text-gray-500" />
            <span className="text-gray-600">Submitted:</span>
            <span className="font-medium text-gray-900">M d, Y g:i a.m.</span>
          </div>
          <div className="flex items-center gap-2">
            <Circle className="h-4 w-4 text-orange-500 fill-orange-500" />
            <span className="text-gray-600">Status:</span>
            <span className="font-medium text-gray-900">Status</span>
          </div>
          <div className="flex items-center gap-2">
            <User className="h-4 w-4 text-gray-500" />
            <span className="text-gray-600">Assigned to:</span>
            <span className="font-medium text-gray-900">User</span>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2 space-y-6">
            <Card className="p-6 bg-white shadow-sm">
              <div className="space-y-6">
                {/* Book cover and title row */}
                <div className="flex gap-6">
                  <div className="flex-shrink-0">
                    <div className="w-40 h-60 rounded-lg overflow-hidden shadow-md bg-gray-100 border border-gray-200">
                      <img 
                        src="https://images.unsplash.com/photo-1656266724419-333cd2502487?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=400"
                        alt="Book cover for How to Lose Friends and Alienate People"
                        className="w-full h-full object-cover"
                      />
                    </div>
                  </div>

                  <div className="flex-1 h-60 flex flex-col">
                    <h3 className="text-2xl font-semibold text-gray-900 leading-tight">
                      Title
                    </h3>
                    <p className="text-base text-gray-600 mt-1 mb-3">by Author</p>
                    <p className="text-sm text-gray-600 leading-relaxed mb-3">
                      Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.{' '}
                      <button className="text-blue-600 hover:text-blue-700 hover:underline font-normal">
                        more
                      </button>
                    </p>
                    <div className="flex items-center gap-2">
                      <Badge variant="outline">Material Type</Badge>
                      <Badge className="bg-purple-100 text-purple-700 hover:bg-purple-100">Audience</Badge>
                      <Badge variant="outline">Genre</Badge>
                    </div>
                  </div>
                </div>

                {/* Metadata grid */}
                <div className="flex items-center gap-6 text-sm text-gray-600">
                  <div className="flex items-center gap-1.5">
                    <BarChart3 className="h-3.5 w-3.5 text-gray-400" />
                    <span>ISBN: 9781598864456</span>
                  </div>
                  <div className="flex items-center gap-1.5">
                    <FileText className="h-3.5 w-3.5 text-gray-400" />
                    <span>Published: Y</span>
                  </div>
                  <div className="flex items-center gap-1.5">
                    <BookOpen className="h-3.5 w-3.5 text-gray-400" />
                    <span>Publisher</span>
                  </div>
                </div>

                {/* Catalog and Editions cards */}
                <div className="grid grid-cols-2 gap-4 pt-4 border-t border-gray-200">
                  <div className="bg-blue-50 rounded-lg p-4 border border-blue-100">
                    <div className="flex items-center gap-1.5 mb-1.5">
                      <Search className="h-4 w-4 text-blue-600" />
                      <p className="text-xs font-medium text-gray-600">Catalog</p>
                    </div>
                    <p className="text-base font-semibold text-gray-900">1 found</p>
                    <p className="text-xs text-gray-600 mt-0.5">Patron rejected</p>
                  </div>

                  <button className="bg-purple-50 rounded-lg p-4 border border-purple-200 hover:bg-purple-100 hover:border-purple-300 transition-colors text-left">
                    <div className="flex items-center gap-1.5 mb-1.5">
                      <BookOpen className="h-4 w-4 text-purple-600" />
                      <p className="text-xs font-medium text-gray-600">Editions</p>
                    </div>
                    <p className="text-base font-semibold text-gray-900">5 found</p>
                    <p className="text-xs text-purple-700 mt-0.5">Click to view →</p>
                  </button>
                </div>
              </div>
            </Card>

            <Card className="p-6 bg-white shadow-sm">
              <div className="flex items-start gap-2 mb-4">
                <User className="h-5 w-5 text-gray-600 mt-0.5" />
                <div className="flex-1">
                  <h2 className="font-semibold text-gray-900">Requested By</h2>
                </div>
                <Button 
                  variant="ghost" 
                  size="sm"
                  onClick={() => setShowPatronDetails(!showPatronDetails)}
                >
                  {showPatronDetails ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                </Button>
              </div>
              
              <div className="flex items-start gap-4">
                <div className="w-12 h-12 rounded-full bg-gradient-to-br from-blue-400 to-indigo-500 flex items-center justify-center flex-shrink-0">
                  <span className="text-white font-semibold">DA</span>
                </div>
                <div className="flex-1">
                  <p className="font-medium text-gray-900">Adults, DCPL</p>
                  <div className="flex items-center gap-4 mt-2 text-sm text-gray-600">
                    <div className="flex items-center gap-1">
                      <Mail className="h-3 w-3" />
                      <span>isalashbrook@gmail.com</span>
                    </div>
                    <div className="flex items-center gap-1">
                      <Phone className="h-3 w-3" />
                      <span>(270) 684-0211</span>
                    </div>
                  </div>
                  
                  {showPatronDetails && (
                    <div className="mt-4 pt-4 border-t border-gray-200 grid grid-cols-2 gap-4">
                      <div>
                        <p className="text-xs text-gray-500">Barcode</p>
                        <p className="text-sm font-medium text-gray-900">23387046d86325</p>
                      </div>
                      <div>
                        <p className="text-xs text-gray-500">Recommended by</p>
                        <p className="text-sm font-medium text-gray-900">Toby Robbins</p>
                      </div>
                      <div>
                        <p className="text-xs text-gray-500">Selection Type</p>
                        <p className="text-sm font-medium text-gray-900">Adult Nonfiction</p>
                      </div>
                      <div className="col-span-2">
                        <Button variant="outline" size="sm">
                          <ExternalLink className="h-3 w-3 mr-2" />
                          View in Polaris Leap
                        </Button>
                      </div>
                    </div>
                  )}
                </div>
              </div>
            </Card>

            <Card className="p-6 bg-white shadow-sm">
              <div className="flex items-center justify-between mb-4">
                <div className="flex items-start gap-2">
                  <Clock className="h-5 w-5 text-gray-600 mt-0.5" />
                  <h2 className="font-semibold text-gray-900">Activity History</h2>
                </div>
                <Button 
                  variant="ghost" 
                  size="sm"
                  onClick={() => setShowHistory(!showHistory)}
                >
                  {showHistory ? (
                    <>
                      <ChevronUp className="h-4 w-4 mr-1" />
                      Hide
                    </>
                  ) : (
                    <>
                      <ChevronDown className="h-4 w-4 mr-1" />
                      Show history
                    </>
                  )}
                </Button>
              </div>
              
              {showHistory && (
                <div className="space-y-3 pt-2">
                  <div className="flex gap-3 text-sm">
                    <div className="flex-shrink-0 w-2 h-2 rounded-full bg-orange-400 mt-1.5"></div>
                    <div className="flex-1">
                      <div className="flex items-center gap-2 mb-1">
                        <span className="font-medium text-gray-900">Pending</span>
                        <span className="text-gray-500">by Brian Lashbrook</span>
                        <span className="text-gray-400 text-xs">Mar 12, 2026 8:26pm</span>
                      </div>
                      <p className="text-gray-600">Auto-claimed on open.</p>
                    </div>
                  </div>
                  
                  <div className="flex gap-3 text-sm bg-gray-50 rounded p-2 -mx-2">
                    <div className="flex-shrink-0 w-2 h-2 rounded-full bg-gray-300 mt-1.5 ml-2"></div>
                    <div className="flex-1">
                      <div className="flex items-center gap-2 mb-1">
                        <span className="font-medium text-gray-700">Auto-claim cycle</span>
                        <span className="text-gray-400 text-xs">3 times between 8:12pm - 8:22pm</span>
                      </div>
                      <p className="text-gray-500 text-xs">Repeated unassign and auto-claim actions by Brian Lashbrook</p>
                    </div>
                  </div>
                  
                  <div className="flex gap-3 text-sm">
                    <div className="flex-shrink-0 w-2 h-2 rounded-full bg-blue-400 mt-1.5"></div>
                    <div className="flex-1">
                      <div className="flex items-center gap-2 mb-1">
                        <span className="font-medium text-gray-900">Pending</span>
                        <span className="text-gray-500">by patron</span>
                        <span className="text-gray-400 text-xs">Mar 12, 2026 8:11pm</span>
                      </div>
                      <p className="text-gray-600">Request submitted by patron.</p>
                    </div>
                  </div>
                </div>
              )}
            </Card>
          </div>

          <div className="space-y-4">
            <Card className="p-5 bg-white shadow-sm border-gray-200 border">
              <h3 className="text-sm font-medium text-gray-700 uppercase tracking-wide mb-3">Update Status</h3>
              
              {showIconSelector && (
                <div className="mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200 space-y-3">
                  <div className="text-xs font-medium text-gray-600 mb-2">Customize Icons</div>
                  
                  <div>
                    <div className="text-xs text-gray-500 mb-1.5">Approve</div>
                    <div className="flex gap-1">
                      {iconOptions.approve.map((option, index) => {
                        const Icon = option.icon;
                        return (
                          <button
                            key={index}
                            onClick={() => setSelectedIcons({...selectedIcons, approve: index})}
                            className={`w-8 h-8 rounded flex items-center justify-center transition-colors ${
                              selectedIcons.approve === index 
                                ? 'bg-green-600 text-white' 
                                : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-100'
                            }`}
                          >
                            <Icon className="h-4 w-4" />
                          </button>
                        );
                      })}
                    </div>
                  </div>

                  <div>
                    <div className="text-xs text-gray-500 mb-1.5">ILL</div>
                    <div className="flex gap-1">
                      {iconOptions.ill.map((option, index) => {
                        const Icon = option.icon;
                        return (
                          <button
                            key={index}
                            onClick={() => setSelectedIcons({...selectedIcons, ill: index})}
                            className={`w-8 h-8 rounded flex items-center justify-center transition-colors ${
                              selectedIcons.ill === index 
                                ? 'bg-blue-600 text-white' 
                                : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-100'
                            }`}
                          >
                            <Icon className="h-4 w-4" />
                          </button>
                        );
                      })}
                    </div>
                  </div>

                  <div>
                    <div className="text-xs text-gray-500 mb-1.5">Deny</div>
                    <div className="flex gap-1">
                      {iconOptions.deny.map((option, index) => {
                        const Icon = option.icon;
                        return (
                          <button
                            key={index}
                            onClick={() => setSelectedIcons({...selectedIcons, deny: index})}
                            className={`w-8 h-8 rounded flex items-center justify-center transition-colors ${
                              selectedIcons.deny === index 
                                ? 'bg-red-600 text-white' 
                                : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-100'
                            }`}
                          >
                            <Icon className="h-4 w-4" />
                          </button>
                        );
                      })}
                    </div>
                  </div>

                  <div>
                    <div className="text-xs text-gray-500 mb-1.5">Pending</div>
                    <div className="flex gap-1">
                      {iconOptions.pending.map((option, index) => {
                        const Icon = option.icon;
                        return (
                          <button
                            key={index}
                            onClick={() => setSelectedIcons({...selectedIcons, pending: index})}
                            className={`w-8 h-8 rounded flex items-center justify-center transition-colors ${
                              selectedIcons.pending === index 
                                ? 'bg-orange-500 text-white' 
                                : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-100'
                            }`}
                          >
                            <Icon className="h-4 w-4" />
                          </button>
                        );
                      })}
                    </div>
                  </div>
                </div>
              )}
              
              <div className="space-y-2">
                <Button 
                  className="w-full justify-start h-11 bg-green-600 hover:bg-green-700 text-white font-medium"
                >
                  <ApproveIcon className="h-4 w-4 mr-2" />
                  Status - Order 1
                </Button>

                <Button 
                  className="w-full justify-start h-11 bg-blue-600 hover:bg-blue-700 text-white font-medium"
                >
                  <IllIcon className="h-4 w-4 mr-2" />
                  Status - Order 2
                </Button>

                <Button 
                  className="w-full justify-start h-11 bg-red-600 hover:bg-red-700 text-white font-medium"
                >
                  <DenyIcon className="h-4 w-4 mr-2" />
                  Status - Order 3
                </Button>
              </div>

              <div className="mt-4 pt-4 border-t border-gray-200">
                <Button 
                  variant="ghost" 
                  className="w-full justify-start text-gray-600 hover:text-gray-900 h-9"
                  size="sm"
                  onClick={() => setShowNote(!showNote)}
                >
                  <MessageSquare className="h-4 w-4 mr-2" />
                  {showNote ? 'Hide note' : 'Add internal note'}
                </Button>

                {showNote && (
                  <Textarea 
                    placeholder="Add an internal note about this decision..." 
                    className="resize-none text-sm mt-2"
                    rows={3}
                  />
                )}
              </div>
            </Card>

            <Card className="p-5 bg-white shadow-sm">
              <h3 className="text-sm font-medium text-gray-700 uppercase tracking-wide mb-3">Quick Actions</h3>
              
              <div className="space-y-2">
                <Button variant="outline" className="w-full justify-start h-10" size="sm">
                  <User className="h-4 w-4 mr-2 text-gray-500" />
                  Reassign
                </Button>

                <Button variant="outline" className="w-full justify-start h-10" size="sm">
                  <Search className="h-4 w-4 mr-2 text-gray-500" />
                  Re-check Catalog
                </Button>
                
                <Button variant="outline" className="w-full justify-start h-10" size="sm">
                  <ExternalLink className="h-4 w-4 mr-2 text-gray-500" />
                  View on Amazon
                </Button>

                <Button variant="outline" className="w-full justify-start h-10" size="sm">
                  <ShoppingCart className="h-4 w-4 mr-2 text-gray-500" />
                  Other Purchase Options
                </Button>

                <Button variant="outline" className="w-full justify-start h-10 text-red-600 hover:text-red-700 hover:bg-red-50 border-red-200" size="sm">
                  <Trash2 className="h-4 w-4 mr-2" />
                  Delete
                </Button>
              </div>
            </Card>
          </div>
        </div>
      </main>
    </div>
  );
}